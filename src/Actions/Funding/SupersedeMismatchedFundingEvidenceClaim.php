<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Funding;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LBHurtado\EmiCore\Models\ProviderFundingObservation;
use LBHurtado\XChange\Enums\FundingIntentPurpose;
use LBHurtado\XChange\Enums\FundingIntentStatus;
use LBHurtado\XChange\Enums\PayCodeIssuanceFundingOrderStatus;
use LBHurtado\XChange\Models\FundingEvidenceClaim;
use LBHurtado\XChange\Models\FundingEvidenceClaimSupersession;
use LBHurtado\XChange\Models\FundingIntent;
use LBHurtado\XChange\Models\FundingReconciliationRequest;
use LBHurtado\XChange\Models\FundingSettlement;
use LBHurtado\XChange\Models\FundingSuspenseCase;
use LBHurtado\XChange\Models\PayCodeIssuanceFundingOrder;
use LBHurtado\XChange\Services\Funding\FundingEvidenceClaimOwnership;

final readonly class SupersedeMismatchedFundingEvidenceClaim
{
    public function __construct(
        private FundingEvidenceClaimOwnership $ownership,
    ) {}

    public function handle(
        FundingReconciliationRequest $request,
        FundingSuspenseCase $targetCase,
        FundingIntent $targetIntent,
        ProviderFundingObservation $observation,
        string $actorType,
        string $actorId,
    ): ?FundingEvidenceClaimSupersession {
        return DB::transaction(function () use (
            $request,
            $targetCase,
            $targetIntent,
            $observation,
            $actorType,
            $actorId,
        ): ?FundingEvidenceClaimSupersession {
            $transactionHash = hash('sha256', implode('|', [
                $observation->provider_code,
                $observation->provider_transaction_id,
            ]));
            $claim = FundingEvidenceClaim::query()
                ->where('provider_code', $observation->provider_code)
                ->where('provider_transaction_hash', $transactionHash)
                ->lockForUpdate()
                ->first();

            if (! $claim instanceof FundingEvidenceClaim
                || $this->ownership->effectiveIntentId($claim, lock: true) === (int) $targetIntent->getKey()) {
                return null;
            }

            if (FundingEvidenceClaimSupersession::query()
                ->where('funding_evidence_claim_id', $claim->getKey())
                ->lockForUpdate()
                ->exists()) {
                throw new InvalidArgumentException('The Funding Evidence Claim was already superseded.');
            }

            $sourceIntent = FundingIntent::query()
                ->lockForUpdate()
                ->findOrFail($claim->funding_intent_id);
            $lockedTargetIntent = FundingIntent::query()
                ->lockForUpdate()
                ->findOrFail($targetIntent->getKey());
            $lockedTargetCase = FundingSuspenseCase::query()
                ->lockForUpdate()
                ->findOrFail($targetCase->getKey());
            $sourceCase = FundingSuspenseCase::query()
                ->where('funding_intent_id', $sourceIntent->getKey())
                ->where('provider_funding_observation_id', $observation->getKey())
                ->where('reason_code', 'on_demand_issuance_excess_payment')
                ->where('status', 'open')
                ->lockForUpdate()
                ->sole();
            $sourceOrder = PayCodeIssuanceFundingOrder::query()
                ->where('funding_intent_id', $sourceIntent->getKey())
                ->lockForUpdate()
                ->sole();
            $targetOrder = PayCodeIssuanceFundingOrder::query()
                ->where('funding_intent_id', $lockedTargetIntent->getKey())
                ->lockForUpdate()
                ->sole();

            $this->assertSafeToSupersede(
                $request,
                $claim,
                $sourceIntent,
                $lockedTargetIntent,
                $sourceCase,
                $lockedTargetCase,
                $sourceOrder,
                $targetOrder,
                $observation,
            );

            $supersession = FundingEvidenceClaimSupersession::query()->create([
                'funding_evidence_claim_id' => $claim->getKey(),
                'from_funding_intent_id' => $sourceIntent->getKey(),
                'to_funding_intent_id' => $lockedTargetIntent->getKey(),
                'provider_funding_observation_id' => $observation->getKey(),
                'funding_reconciliation_request_id' => $request->getKey(),
                'source_suspense_case_id' => $sourceCase->getKey(),
                'target_suspense_case_id' => $lockedTargetCase->getKey(),
                'reason_code' => 'pre_repair_mismatch_claim_corrected',
                'approved_by_type' => $actorType,
                'approved_by_id' => $actorId,
                'superseded_at' => now(),
            ]);

            $sourceCase->forceFill([
                'status' => 'resolved',
                'resolved_at' => now(),
                'resolved_by_type' => $actorType,
                'resolved_by_id' => $actorId,
                'resolution_code' => 'evidence_claim_superseded',
                'resolution' => [
                    'funding_evidence_claim_id' => $claim->getKey(),
                    'funding_evidence_claim_supersession_id' => $supersession->getKey(),
                    'effective_funding_intent_id' => $lockedTargetIntent->getKey(),
                ],
            ])->saveQuietly();

            return $supersession;
        }, attempts: 3);
    }

    private function assertSafeToSupersede(
        FundingReconciliationRequest $request,
        FundingEvidenceClaim $claim,
        FundingIntent $sourceIntent,
        FundingIntent $targetIntent,
        FundingSuspenseCase $sourceCase,
        FundingSuspenseCase $targetCase,
        PayCodeIssuanceFundingOrder $sourceOrder,
        PayCodeIssuanceFundingOrder $targetOrder,
        ProviderFundingObservation $observation,
    ): void {
        $terminalStatuses = [
            PayCodeIssuanceFundingOrderStatus::Cancelled,
            PayCodeIssuanceFundingOrderStatus::Expired,
        ];
        $safe = $request->funding_suspense_case_id === $targetCase->getKey()
            && $claim->provider_funding_observation_id === $observation->getKey()
            && $sourceIntent->purpose === FundingIntentPurpose::OnDemandIssuance
            && $targetIntent->purpose === FundingIntentPurpose::OnDemandIssuance
            && $sourceIntent->status === FundingIntentStatus::Suspense
            && $targetIntent->status === FundingIntentStatus::Suspense
            && $sourceIntent->account_reference === $targetIntent->account_reference
            && $sourceIntent->provider_code === $targetIntent->provider_code
            && $sourceIntent->currency === $targetIntent->currency
            && $sourceIntent->expected_amount_minor !== $observation->gross_amount_minor
            && $targetIntent->expected_amount_minor === $observation->gross_amount_minor
            && $sourceCase->reason_code === 'on_demand_issuance_excess_payment'
            && $targetCase->reason_code === 'on_demand_issuance_duplicate_evidence'
            && $targetCase->status === 'open'
            && in_array($sourceOrder->status, $terminalStatuses, true)
            && in_array($targetOrder->status, $terminalStatuses, true)
            && $sourceOrder->voucher_id === null
            && $targetOrder->voucher_id === null
            && $sourceOrder->treasury_hold_reference === null
            && $targetOrder->treasury_hold_reference === null
            && ! FundingSettlement::query()->whereIn('funding_intent_id', [
                $sourceIntent->getKey(),
                $targetIntent->getKey(),
            ])->exists();

        if (! $safe) {
            throw new InvalidArgumentException(
                'The pre-repair evidence claim does not satisfy the guarded supersession contract.',
            );
        }
    }
}
