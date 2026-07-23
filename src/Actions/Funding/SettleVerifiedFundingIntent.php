<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Funding;

use Bavix\Wallet\Models\Transaction;
use Illuminate\Support\Facades\DB;
use LBHurtado\EmiCore\Models\ProviderFundingObservation;
use LBHurtado\Wallet\Treasury\Contracts\TreasuryInventoryOperationContract;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryData;
use LBHurtado\Wallet\Treasury\Data\TreasuryInventoryRecognitionData;
use LBHurtado\XChange\Contracts\FundingAccountCreditContract;
use LBHurtado\XChange\Data\Funding\FundingIntentTransitionData;
use LBHurtado\XChange\Enums\FundingIntentStatus;
use LBHurtado\XChange\Exceptions\FundingSettlementDenied;
use LBHurtado\XChange\Models\FundingIntent;
use LBHurtado\XChange\Models\FundingSettlement;

class SettleVerifiedFundingIntent
{
    public function __construct(
        private readonly TreasuryInventoryOperationContract $treasury,
        private readonly FundingAccountCreditContract $accounts,
        private readonly TransitionFundingIntent $transition,
        private readonly ApplyFundingRecoveryToAccount $applyRecovery,
    ) {}

    public function handle(FundingIntent $intent): FundingSettlement
    {
        return DB::transaction(function () use ($intent): FundingSettlement {
            $locked = FundingIntent::query()->lockForUpdate()->findOrFail($intent->getKey());
            $existing = FundingSettlement::query()
                ->where('funding_intent_id', $locked->getKey())
                ->first();

            if ($existing instanceof FundingSettlement) {
                return $existing;
            }

            if ($locked->status !== FundingIntentStatus::Verified) {
                throw FundingSettlementDenied::because('the Funding Intent is not verified');
            }

            $observation = $this->verifiedObservation($locked);
            $treasury = $this->treasuryConfiguration($locked->provider_code);
            $operationReference = 'funding-recognition:'.hash('sha256', $locked->reference);
            $idempotencyKey = 'funding-recognition-key:'.hash('sha256', $locked->reference);
            $externalReference = $locked->provider_code.':'.$observation->provider_transaction_id;

            $this->treasury->registerInventory(new TreasuryInventoryData(
                inventoryReference: $treasury['inventory_reference'],
                resourceType: $treasury['resource_type'],
                currency: $locked->currency,
                capacityMinor: 0,
                status: 'requested',
                idempotencyKey: 'register:'.$treasury['inventory_reference'],
                externalReference: $treasury['settlement_resource_reference'],
                metadata: [
                    'provider' => $locked->provider_code,
                    'source' => 'x-change.funding',
                ],
            ));

            $recognition = $this->treasury->recognize(new TreasuryInventoryRecognitionData(
                operationReference: $operationReference,
                inventoryReference: $treasury['inventory_reference'],
                settlementResourceReference: $treasury['settlement_resource_reference'],
                amountMinor: $observation->net_amount_minor,
                currency: $observation->currency,
                status: 'requested',
                idempotencyKey: $idempotencyKey,
                effectiveAt: $observation->settled_at?->toRfc3339String(),
                externalReference: $externalReference,
                metadata: [
                    'funding_intent_reference' => $locked->reference,
                    'provider_observation_id' => $observation->getKey(),
                    'gross_amount_minor' => $observation->gross_amount_minor,
                    'fee_amount_minor' => $observation->fee_amount_minor,
                ],
            ));

            $account = $this->accounts->resolve($locked->account_reference);
            $transaction = $this->accounts->credit($account, $observation->net_amount_minor, [
                'source' => 'verified_provider_funding',
                'funding_intent_reference' => $locked->reference,
                'provider' => $locked->provider_code,
                'provider_transaction_id' => $observation->provider_transaction_id,
                'provider_observation_id' => $observation->getKey(),
                'treasury_operation_reference' => $recognition->operationReference,
            ]);

            if (! $transaction instanceof Transaction) {
                throw FundingSettlementDenied::because('the Account ledger did not return a wallet transaction');
            }

            $settlement = FundingSettlement::query()->create([
                'funding_intent_id' => $locked->getKey(),
                'provider_funding_observation_id' => $observation->getKey(),
                'provider_code' => $locked->provider_code,
                'account_reference' => $locked->account_reference,
                'gross_amount_minor' => $observation->gross_amount_minor,
                'fee_amount_minor' => $observation->fee_amount_minor,
                'net_amount_minor' => $observation->net_amount_minor,
                'currency' => $observation->currency,
                'treasury_inventory_reference' => $treasury['inventory_reference'],
                'treasury_operation_reference' => $recognition->operationReference,
                'wallet_transaction_id' => $transaction->getKey(),
                'wallet_transaction_uuid' => $transaction->uuid,
                'settled_at' => now(),
                'metadata' => [
                    'settlement_resource_reference' => $treasury['settlement_resource_reference'],
                    'provider_verification_source' => $observation->verification_source,
                ],
            ]);
            $recoveryAppliedAmountMinor = $this->applyRecovery->handle(
                accountReference: $locked->account_reference,
                account: $account,
                settlement: $settlement,
            );

            $this->transition->handle($locked, new FundingIntentTransitionData(
                status: FundingIntentStatus::Settled,
                eventType: 'account_funding_settled',
                actorType: 'funding_settlement_runtime',
                actorId: $locked->provider_code,
                expectedVersion: $locked->version,
                evidenceReference: 'funding-settlement:'.$settlement->getKey(),
                providerObservationId: $observation->getKey(),
                providerTransactionId: $observation->provider_transaction_id,
                metadata: [
                    'net_amount_minor' => $observation->net_amount_minor,
                    'recovery_applied_amount_minor' => $recoveryAppliedAmountMinor,
                    'treasury_operation_reference' => $recognition->operationReference,
                    'wallet_transaction_uuid' => $transaction->uuid,
                ],
            ));

            return $settlement;
        }, attempts: 5);
    }

    private function verifiedObservation(FundingIntent $intent): ProviderFundingObservation
    {
        $observation = ProviderFundingObservation::query()
            ->whereKey($intent->matched_observation_id)
            ->lockForUpdate()
            ->first();

        $matches = $observation instanceof ProviderFundingObservation
            && $observation->provider_code === $intent->provider_code
            && $observation->provider_transaction_id === $intent->provider_transaction_id
            && $observation->provider_status === 'settled'
            && $observation->gross_amount_minor === $intent->expected_amount_minor
            && $observation->currency === $intent->currency
            && $observation->net_amount_minor > 0
            && $observation->settled_at !== null
            && data_get($observation->metadata, 'destination_verified') === true;

        if (! $matches) {
            throw FundingSettlementDenied::because('authoritative provider evidence no longer matches the intent');
        }

        return $observation;
    }

    /**
     * @return array{inventory_reference: string, settlement_resource_reference: string, resource_type: string}
     */
    private function treasuryConfiguration(string $provider): array
    {
        $configuration = config("x-change.funding.providers.{$provider}.treasury");

        if (! is_array($configuration)) {
            throw FundingSettlementDenied::because('the provider Treasury Inventory is not configured');
        }

        foreach (['inventory_reference', 'settlement_resource_reference', 'resource_type'] as $key) {
            if (! is_string($configuration[$key] ?? null) || trim($configuration[$key]) === '') {
                throw FundingSettlementDenied::because("the provider Treasury {$key} is not configured");
            }
        }

        return $configuration;
    }
}
