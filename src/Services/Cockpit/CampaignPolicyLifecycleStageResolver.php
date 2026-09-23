<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

use LBHurtado\XChange\Enums\PolicyCompletionOutcomeStatus;
use LBHurtado\XChange\Enums\PolicyCompletionRequestStatus;

final class CampaignPolicyLifecycleStageResolver
{
    /** @return array{stage: string, attention_required: bool} */
    public function resolve(
        bool $issuanceExists,
        bool $projectionExists,
        ?PolicyCompletionRequestStatus $requestStatus,
        ?PolicyCompletionOutcomeStatus $outcomeStatus,
    ): array {
        $terminalStatus = $outcomeStatus?->value ?? ($requestStatus?->terminal() ? $requestStatus->value : null);

        if ($terminalStatus !== null) {
            return [
                'stage' => 'policy_'.$terminalStatus,
                'attention_required' => in_array($terminalStatus, ['failed', 'indeterminate'], true),
            ];
        }

        if ($requestStatus === PolicyCompletionRequestStatus::Authorized) {
            return ['stage' => 'policy_authorized', 'attention_required' => false];
        }

        if ($requestStatus === PolicyCompletionRequestStatus::AwaitingApproval) {
            return ['stage' => 'policy_awaiting_approval', 'attention_required' => false];
        }

        if ($projectionExists) {
            return ['stage' => 'claim_evidence_ready', 'attention_required' => false];
        }

        if ($issuanceExists) {
            return ['stage' => 'awaiting_completion_claim', 'attention_required' => false];
        }

        return ['stage' => 'provisional_coverage_active', 'attention_required' => false];
    }
}
