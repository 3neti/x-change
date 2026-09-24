<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Settlement;

use DomainException;
use LBHurtado\XChange\Models\CompletionClaimEvidenceProjection;
use LBHurtado\XChange\Models\PolicyCompletionRequest;

final class AutomaticDemonstrationPolicy
{
    public const MODE = 'automatic_demo';

    public function enabledFor(CompletionClaimEvidenceProjection $projection): bool
    {
        $projection->loadMissing('issuance.coverage.campaign');

        return (bool) config('x-change.settlement.policy_completion.automatic_demo.enabled', false)
            && $projection->issuance->driver_id === AuiPersonalAccidentPolicyCompletionDriver::DRIVER_ID
            && $projection->issuance->driver_version === AuiPersonalAccidentPolicyCompletionDriver::DRIVER_VERSION
            && filled($projection->issuance->coverage->campaign?->reference)
            && in_array($projection->issuance->coverage->campaign?->reference,
                (array) config('x-change.settlement.policy_completion.automatic_demo.campaign_references', []), true);
    }

    public function assertEnabled(CompletionClaimEvidenceProjection $projection): void
    {
        if (! $this->enabledFor($projection)) {
            throw new DomainException('Automatic demonstration completion is not enabled for this campaign.');
        }
    }

    public function matches(PolicyCompletionRequest $request): bool
    {
        if (data_get($request->safe_context, 'authorization_mode') !== self::MODE) {
            return false;
        }
        $owner = $request->projection->issuance->coverage->recognition->ownerRecord();

        return $request->requester_type === $owner->getMorphClass()
            && (string) $request->requester_id === (string) $owner->getKey()
            && $request->authorization_reference === 'automatic-demo:'.$request->projection->reference
            && $request->approved_at === null && $request->approval_reference === null
            && $request->approver_type === null && $request->approver_id === null;
    }
}
