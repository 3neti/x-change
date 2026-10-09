<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Settlement;

use Illuminate\Support\Facades\URL;
use InvalidArgumentException;
use LBHurtado\XChange\Actions\Settlement\PrepareCampaignPolicyCompletion;
use LBHurtado\XChange\Enums\PolicyCompletionOutcomeStatus;
use LBHurtado\XChange\Enums\PolicyCompletionRequestStatus;
use LBHurtado\XChange\Models\PolicyCompletionOutcome;
use LBHurtado\XChange\Models\PolicyCompletionRequest;

final class DemonstrationPolicySummary
{
    public function __construct(
        private readonly AutomaticDemonstrationPolicy $automaticDemonstration,
        private readonly CampaignDemonstrationProductPresentation $presentations,
    ) {}

    public function eligible(PolicyCompletionOutcome $outcome): bool
    {
        if (! config('x-change.settlement.policy_completion.demonstration_summary.enabled', false)) {
            return false;
        }

        $outcome->loadMissing('request.projection.issuance.coverage');
        $request = $outcome->request;
        $coverage = $request?->projection?->issuance?->coverage;
        $presentation = $request === null ? null : $this->presentations->forDriver(
            $request->driver_id,
            $request->driver_version,
        );

        return $request !== null && $coverage !== null && $presentation !== null
            && $outcome->status === PolicyCompletionOutcomeStatus::Succeeded
            && $request->status === PolicyCompletionRequestStatus::Succeeded
            && ($this->automaticDemonstration->matches($request) || $this->independentlyApproved($request))
            && $coverage->driver_id === $request->driver_id
            && $coverage->driver_version === $request->driver_version
            && $outcome->result_code === $presentation['result_code']
            && data_get($outcome->safe_result, 'decision') === $presentation['decision']
            && data_get($outcome->safe_result, 'provider_status') === $presentation['decision']
            && data_get($outcome->safe_result, 'reason_code') === 'demonstration_only'
            && data_get($outcome->safe_result, 'document_ready') === false
            && str_starts_with((string) $outcome->provider_reference, $presentation['reference_prefix'])
            && $outcome->recorded_at !== null;
    }

    private function independentlyApproved(PolicyCompletionRequest $request): bool
    {
        return $request->approved_at !== null
            && filled($request->approval_reference)
            && filled($request->approver_type) && filled($request->approver_id)
            && filled($request->requester_type) && filled($request->requester_id)
            && ! ($request->approver_type === $request->requester_type
                && (string) $request->approver_id === (string) $request->requester_id);
    }

    public function url(PolicyCompletionOutcome $outcome): ?string
    {
        if (! $this->eligible($outcome)) {
            return null;
        }

        $expiresAt = $outcome->recorded_at->addHours(max(1, min(720, (int) config(
            'x-change.settlement.policy_completion.demonstration_summary.link_ttl_hours', 168,
        ))));
        if ($expiresAt->isPast()) {
            return null;
        }

        return URL::temporarySignedRoute('x-change.demo-policy.show', $expiresAt, ['outcome' => $outcome->reference]);
    }

    /** @return array<string, string|null> */
    public function present(PolicyCompletionOutcome $outcome): array
    {
        abort_unless($this->eligible($outcome), 404);
        $coverage = $outcome->request->projection->issuance->coverage;
        $presentation = $this->presentations->forDriver(
            $outcome->request->driver_id,
            $outcome->request->driver_version,
        );

        return [
            'reference' => $outcome->provider_reference,
            'product' => $presentation['product'],
            'effective_at' => $coverage->effective_at?->toIso8601String(),
            'expires_at' => $coverage->expires_at?->toIso8601String(),
            'recorded_at' => $outcome->recorded_at->toIso8601String(),
            'notice' => $presentation['notice'],
            'eyebrow' => $presentation['eyebrow'],
            'title' => $presentation['title'],
            'description' => $presentation['description'],
            'action_label' => $presentation['action_label'],
            'action_description' => $presentation['action_description'],
        ];
    }

    /** @return array<string, string|null> */
    public function privateApplicantDetails(PolicyCompletionOutcome $outcome): array
    {
        abort_unless($this->eligible($outcome), 404);

        try {
            $evidence = app(PrepareCampaignPolicyCompletion::class)
                ->handle($outcome->request->projection)->privateApplicantEvidence();
        } catch (InvalidArgumentException) {
            abort(404);
        }

        $details = [];
        foreach (['name', 'address', 'birth_date', 'mobile', 'email'] as $key) {
            $value = $evidence[$key] ?? null;
            $details[$key] = is_string($value) && trim($value) !== '' ? trim($value) : null;
        }

        return $details;
    }
}
