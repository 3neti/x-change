<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Funding;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LBHurtado\EmiCore\Models\ProviderFundingObservation;
use LBHurtado\XChange\Data\Funding\FundingIntentTransitionData;
use LBHurtado\XChange\Enums\FundingIntentStatus;
use LBHurtado\XChange\Enums\PayCodeIssuanceFundingOrderStatus;
use LBHurtado\XChange\Exceptions\FundingEvidenceAlreadyClaimed;
use LBHurtado\XChange\Models\FundingIntent;
use LBHurtado\XChange\Models\FundingSuspenseCase;
use LBHurtado\XChange\Models\PayCodeIssuanceFundingOrder;

final readonly class ExecutePaidOnDemandIssuanceRecovery
{
    public function __construct(
        private AssessPaidOnDemandIssuanceRecovery $assessment,
        private ClaimFundingEvidence $claim,
        private TransitionPayCodeIssuanceFundingOrder $transitionOrder,
        private TransitionFundingIntent $transitionIntent,
        private SettleVerifiedFundingIntent $settle,
        private LockIssuanceFundingAttribution $attributionLock,
    ) {}

    /** @return array{outcome: string, funding_settlement_id: int, funding_order_id: int} */
    public function handle(FundingSuspenseCase $case, FundingIntent $intent, int $observationId, string $actorType, string $actorId): array
    {
        return DB::transaction(function () use ($case, $intent, $observationId, $actorType, $actorId): array {
            $this->attributionLock->handle($intent->provider_code, $intent->currency);
            $lockedIntent = FundingIntent::query()->lockForUpdate()->findOrFail($intent->getKey());
            $lockedCase = FundingSuspenseCase::query()->lockForUpdate()->findOrFail($case->getKey());
            $observation = ProviderFundingObservation::query()->lockForUpdate()->findOrFail($observationId);
            $order = PayCodeIssuanceFundingOrder::query()->where('funding_intent_id', $lockedIntent->getKey())->lockForUpdate()->firstOrFail();

            if (! $this->assessment->matches($lockedCase, $lockedIntent, $observation, $order)) {
                throw new InvalidArgumentException('This payment is no longer eligible for Pay Code recovery.');
            }

            try {
                $this->claim->handle($lockedIntent, $observation);
            } catch (FundingEvidenceAlreadyClaimed) {
                throw new InvalidArgumentException('This provider payment already belongs to another funding order.');
            }
            $this->transitionOrder->handle(
                order: $order,
                status: PayCodeIssuanceFundingOrderStatus::Verifying,
                eventType: 'reconciliation_paid_order_recovery_approved',
                actorType: $actorType,
                actorId: $actorId,
                attributes: ['expired_at' => null, 'attention_at' => null],
                metadata: ['provider_observation_id' => $observation->getKey()],
                approvedPreExpiryPaymentRecovery: true,
            );
            $lockedIntent = $this->transitionIntent->handle($lockedIntent, new FundingIntentTransitionData(
                status: FundingIntentStatus::Verifying,
                eventType: 'reconciliation_paid_order_recovery_approved',
                actorType: $actorType,
                actorId: $actorId,
                expectedVersion: $lockedIntent->version,
                evidenceReference: 'provider-observation:'.$observation->getKey(),
                metadata: ['funding_reconciliation_case_id' => $lockedCase->getKey()],
            ));
            $lockedIntent = $this->transitionIntent->handle($lockedIntent, new FundingIntentTransitionData(
                status: FundingIntentStatus::Verified,
                eventType: 'reconciliation_provider_settlement_verified',
                actorType: $actorType,
                actorId: $actorId,
                expectedVersion: $lockedIntent->version,
                evidenceReference: 'provider-observation:'.$observation->getKey(),
                providerObservationId: $observation->getKey(),
                providerTransactionId: $observation->provider_transaction_id,
                metadata: ['funding_reconciliation_case_id' => $lockedCase->getKey()],
            ));
            $settlement = $this->settle->handle($lockedIntent);
            $lockedCase->forceFill([
                'status' => 'resolved',
                'resolved_at' => now(),
                'resolved_by_type' => $actorType,
                'resolved_by_id' => $actorId,
                'resolution_code' => 'paid_order_recovery_approved',
                'resolution' => [
                    'funding_settlement_id' => $settlement->getKey(),
                    'provider_observation_id' => $observation->getKey(),
                    'funding_order_id' => $order->getKey(),
                ],
            ])->saveQuietly();

            return ['outcome' => 'pay_code_recovery_queued', 'funding_settlement_id' => (int) $settlement->getKey(), 'funding_order_id' => (int) $order->getKey()];
        }, attempts: 5);
    }
}
