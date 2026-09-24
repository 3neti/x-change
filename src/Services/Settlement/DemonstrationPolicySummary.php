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
    public function eligible(PolicyCompletionOutcome $outcome): bool
    {
        if (! config('x-change.settlement.policy_completion.demonstration_summary.enabled', false)) {
            return false;
        }

        $outcome->loadMissing('request.projection.issuance.coverage');
        $request = $outcome->request;
        $coverage = $request?->projection?->issuance?->coverage;

        return $request !== null && $coverage !== null
            && $outcome->status === PolicyCompletionOutcomeStatus::Succeeded
            && $request->status === PolicyCompletionRequestStatus::Succeeded
            && ((new AutomaticDemonstrationPolicy)->matches($request) || $this->independentlyApproved($request))
            && $request->driver_id === AuiPersonalAccidentPolicyCompletionDriver::DRIVER_ID
            && $request->driver_version === AuiPersonalAccidentPolicyCompletionDriver::DRIVER_VERSION
            && $coverage->driver_id === $request->driver_id
            && $coverage->driver_version === $request->driver_version
            && $outcome->result_code === 'policy_issued_demo'
            && data_get($outcome->safe_result, 'decision') === 'issued_demo'
            && data_get($outcome->safe_result, 'provider_status') === 'issued_demo'
            && data_get($outcome->safe_result, 'reason_code') === 'demonstration_only'
            && data_get($outcome->safe_result, 'document_ready') === false
            && preg_match('/^AUI-DEMO-[A-Za-z0-9-]{1,100}$/', (string) $outcome->provider_reference) === 1
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

        return [
            'reference' => $outcome->provider_reference,
            'product' => 'Personal Accident — demonstration',
            'effective_at' => $coverage->effective_at?->toIso8601String(),
            'expires_at' => $coverage->expires_at?->toIso8601String(),
            'recorded_at' => $outcome->recorded_at->toIso8601String(),
            'notice' => 'Demonstration only. This is not an issued insurance policy and does not establish insurance coverage.',
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
