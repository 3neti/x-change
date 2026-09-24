<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use LBHurtado\XChange\Data\Settlement\CampaignPolicyLifecycleData;
use LBHurtado\XChange\Models\LeadCampaign;
use LBHurtado\XChange\Models\ProvisionalCoverage;

final readonly class CampaignPolicyLifecycleReadModel
{
    public function __construct(private CampaignPolicyLifecycleStageResolver $stages) {}

    /** @return list<CampaignPolicyLifecycleData> */
    public function forOwner(Model $owner, int $limit = 50, ?string $campaignReference = null): array
    {
        $campaignIds = LeadCampaign::query()
            ->select('id')
            ->where('owner_type', $owner->getMorphClass())
            ->where('owner_id', (string) $owner->getKey())
            ->when($campaignReference !== null, fn ($query) => $query->where('reference', $campaignReference));

        return ProvisionalCoverage::query()
            ->select(['id', 'reference', 'campaign_payment_recognition_id', 'endpoint_campaign_id', 'campaign_revision_id', 'status', 'coverage_type', 'coverage_amount_minor', 'currency', 'effective_at', 'expires_at', 'bound_at'])
            ->whereIn('endpoint_campaign_id', $campaignIds)
            ->with([
                'campaign:id,reference,title',
                'recognition:id,reference,gross_amount_minor,currency,settled_at',
                'completionPayCodeIssuance:id,reference,provisional_coverage_id,voucher_id,issued_at',
                'completionPayCodeIssuance.voucher:id,code',
                'completionPayCodeIssuance.evidenceProjection:id,reference,completion_pay_code_issuance_id,voucher_claim_id,projected_at',
                'completionPayCodeIssuance.evidenceProjection.claim:id,claim_number,completed_at',
                'completionPayCodeIssuance.evidenceProjection.policyCompletionRequest:id,reference,completion_claim_evidence_projection_id,status,requested_at,approved_at',
                'completionPayCodeIssuance.evidenceProjection.policyCompletionRequest.outcome:id,reference,policy_completion_request_id,status,result_code,recorded_at',
            ])
            ->latest('bound_at')
            ->limit(max(1, min($limit, 100)))
            ->get()
            ->map(fn (ProvisionalCoverage $coverage): CampaignPolicyLifecycleData => $this->project($coverage))
            ->all();
    }

    private function project(ProvisionalCoverage $coverage): CampaignPolicyLifecycleData
    {
        $issuance = $coverage->completionPayCodeIssuance;
        $projection = $issuance?->evidenceProjection;
        $request = $projection?->policyCompletionRequest;
        $outcome = $request?->outcome;
        $stage = $this->stages->resolve(
            $issuance !== null,
            $projection !== null,
            $request?->status,
            $outcome?->status,
        );

        return new CampaignPolicyLifecycleData(
            stage: $stage['stage'],
            attentionRequired: $stage['attention_required'],
            campaign: [
                'reference' => $coverage->campaign?->reference,
                'name' => $coverage->campaign?->title,
                'revision_id' => $coverage->campaign_revision_id,
            ],
            payment: [
                'recognition_reference' => $coverage->recognition?->reference,
                'gross_amount_minor' => $coverage->recognition?->gross_amount_minor,
                'currency' => $coverage->recognition?->currency,
                'settled_at' => $coverage->recognition?->settled_at?->toIso8601String(),
            ],
            coverage: [
                'reference' => $coverage->reference,
                'type' => $coverage->coverage_type,
                'status' => $coverage->status->value,
                'amount_minor' => $coverage->coverage_amount_minor,
                'currency' => $coverage->currency,
                'effective_at' => $coverage->effective_at?->toIso8601String(),
                'expires_at' => $coverage->expires_at?->toIso8601String(),
            ],
            completion: $issuance === null ? null : [
                'issuance_reference' => $issuance->reference,
                'pay_code' => $issuance->voucher?->code,
                'issued_at' => $issuance->issued_at?->toIso8601String(),
                'claim_number' => $projection?->claim?->claim_number,
                'claim_completed_at' => $projection?->claim?->completed_at?->toIso8601String(),
                'projection_reference' => $projection?->reference,
                'projected_at' => $projection?->projected_at?->toIso8601String(),
            ],
            policy: $request === null ? null : [
                'request_reference' => $request->reference,
                'status' => $request->status->value,
                'requested_at' => $request->requested_at?->toIso8601String(),
                'approved_at' => $request->approved_at?->toIso8601String(),
                'outcome_reference' => $outcome?->reference,
                'outcome_status' => $outcome?->status->value,
                'result_code' => $outcome?->result_code,
                'recorded_at' => $outcome?->recorded_at?->toIso8601String(),
            ],
            updatedAt: $this->latestTimestamp([
                $coverage->bound_at,
                $coverage->recognition?->settled_at,
                $issuance?->issued_at,
                $projection?->projected_at,
                $request?->requested_at,
                $request?->approved_at,
                $outcome?->recorded_at,
            ]),
        );
    }

    /** @param array<int, CarbonInterface|null> $timestamps */
    private function latestTimestamp(array $timestamps): ?string
    {
        return Collection::make($timestamps)
            ->filter()
            ->sortByDesc(fn (CarbonInterface $timestamp): int => $timestamp->getTimestamp())
            ->first()?->toIso8601String();
    }
}
