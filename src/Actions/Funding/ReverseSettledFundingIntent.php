<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Funding;

use Illuminate\Support\Facades\DB;
use LBHurtado\EmiCore\Models\ProviderFundingObservation;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryInventoryOperationContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryOperationReversalData;
use LBHurtado\XChange\Contracts\FundingAccountRecoveryContract;
use LBHurtado\XChange\Contracts\TreasuryPositionLedgerResolverContract;
use LBHurtado\XChange\Data\Funding\FundingIntentTransitionData;
use LBHurtado\XChange\Enums\FundingIntentStatus;
use LBHurtado\XChange\Exceptions\FundingSettlementDenied;
use LBHurtado\XChange\Models\FundingAccountHold;
use LBHurtado\XChange\Models\FundingIntent;
use LBHurtado\XChange\Models\FundingRecovery;
use LBHurtado\XChange\Models\FundingSettlement;

class ReverseSettledFundingIntent
{
    public function __construct(
        private readonly TreasuryInventoryOperationContract $treasury,
        private readonly FundingAccountRecoveryContract $accounts,
        private readonly TreasuryPositionLedgerResolverContract $positionLedgers,
        private readonly TransitionFundingIntent $transition,
    ) {}

    public function handle(
        FundingIntent $intent,
        ProviderFundingObservation $reversalObservation,
    ): FundingRecovery {
        return DB::transaction(function () use ($intent, $reversalObservation): FundingRecovery {
            $locked = FundingIntent::query()->lockForUpdate()->findOrFail($intent->getKey());
            $existing = FundingRecovery::query()
                ->where('funding_intent_id', $locked->getKey())
                ->first();

            if ($existing instanceof FundingRecovery) {
                return $existing;
            }

            if ($locked->status !== FundingIntentStatus::Settled) {
                throw FundingSettlementDenied::because('the Funding Intent is not settled');
            }

            $settlement = FundingSettlement::query()
                ->where('funding_intent_id', $locked->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $observation = ProviderFundingObservation::query()
                ->whereKey($reversalObservation->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertReversalMatches($locked, $settlement, $observation);

            $operationReference = 'funding-reversal:'.hash(
                'sha256',
                $locked->reference.'|'.$observation->getKey(),
            );
            $treasuryReversal = $this->treasury->reverse(new TreasuryOperationReversalData(
                operationReference: $operationReference,
                reversesOperationReference: $settlement->treasury_operation_reference,
                amountMinor: $settlement->net_amount_minor,
                currency: $settlement->currency,
                status: 'requested',
                idempotencyKey: 'funding-reversal-key:'.hash('sha256', $locked->reference),
                effectiveAt: $observation->occurred_at?->toRfc3339String(),
                externalReference: $locked->provider_code.':'.$observation->provider_transaction_id.':reversal',
                metadata: [
                    'funding_intent_reference' => $locked->reference,
                    'funding_settlement_id' => $settlement->getKey(),
                    'reversal_observation_id' => $observation->getKey(),
                ],
            ));

            $positionReference = data_get(
                $settlement->metadata,
                'treasury_destination_position_reference',
            );
            $account = is_string($positionReference) && trim($positionReference) !== ''
                ? $this->positionLedgers->resolve($positionReference)
                : $this->accounts->resolve($locked->account_reference);
            $accountRecovery = $this->accounts->recover($account, $settlement->net_amount_minor, [
                'source' => 'provider_funding_reversal',
                'funding_intent_reference' => $locked->reference,
                'provider' => $locked->provider_code,
                'provider_transaction_id' => $observation->provider_transaction_id,
                'reversal_observation_id' => $observation->getKey(),
                'treasury_operation_reference' => $treasuryReversal->operationReference,
            ]);
            $now = now();
            $recovery = FundingRecovery::query()->create([
                'funding_intent_id' => $locked->getKey(),
                'funding_settlement_id' => $settlement->getKey(),
                'reversal_observation_id' => $observation->getKey(),
                'account_reference' => $locked->account_reference,
                'reversal_amount_minor' => $settlement->net_amount_minor,
                'recovered_amount_minor' => $accountRecovery->recoveredAmountMinor,
                'outstanding_amount_minor' => $accountRecovery->outstandingAmountMinor,
                'currency' => $settlement->currency,
                'treasury_reversal_operation_reference' => $treasuryReversal->operationReference,
                'wallet_transaction_id' => $accountRecovery->walletTransactionId,
                'wallet_transaction_uuid' => $accountRecovery->walletTransactionUuid,
                'status' => $accountRecovery->outstandingAmountMinor === 0 ? 'recovered' : 'open',
                'opened_at' => $now,
                'recovered_at' => $accountRecovery->outstandingAmountMinor === 0 ? $now : null,
                'metadata' => [
                    'provider_status' => $observation->provider_status,
                ],
            ]);

            if ($accountRecovery->outstandingAmountMinor > 0) {
                FundingAccountHold::query()->create([
                    'funding_recovery_id' => $recovery->getKey(),
                    'account_reference' => $locked->account_reference,
                    'outstanding_amount_minor' => $accountRecovery->outstandingAmountMinor,
                    'currency' => $settlement->currency,
                    'status' => 'active',
                    'placed_at' => $now,
                    'metadata' => [
                        'reason' => 'provider_funding_reversal_deficit',
                    ],
                ]);
            }

            $this->transition->handle($locked, new FundingIntentTransitionData(
                status: FundingIntentStatus::Reversed,
                eventType: 'provider_funding_reversed',
                actorType: 'funding_recovery_runtime',
                actorId: $locked->provider_code,
                expectedVersion: $locked->version,
                evidenceReference: 'provider-observation:'.$observation->getKey(),
                providerObservationId: $observation->getKey(),
                providerTransactionId: $observation->provider_transaction_id,
                metadata: [
                    'funding_recovery_id' => $recovery->getKey(),
                    'recovered_amount_minor' => $accountRecovery->recoveredAmountMinor,
                    'outstanding_amount_minor' => $accountRecovery->outstandingAmountMinor,
                ],
            ));

            return $recovery;
        }, attempts: 5);
    }

    private function assertReversalMatches(
        FundingIntent $intent,
        FundingSettlement $settlement,
        ProviderFundingObservation $observation,
    ): void {
        $matches = $observation->provider_code === $intent->provider_code
            && $observation->provider_transaction_id === $intent->provider_transaction_id
            && in_array($observation->provider_status, ['reversed', 'refunded', 'charged_back'], true)
            && $observation->gross_amount_minor === $settlement->gross_amount_minor
            && $observation->net_amount_minor === $settlement->net_amount_minor
            && $observation->currency === $settlement->currency
            && $observation->occurred_at !== null;

        if (! $matches) {
            throw FundingSettlementDenied::because('authoritative reversal evidence does not match the settlement');
        }
    }
}
