<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

use LBHurtado\SettlementEnvelope\Contracts\WorkflowAccessPolicy;
use LBHurtado\SettlementEnvelope\Data\WorkflowContext;

final class ConfiguredCampaignWorkflowAccess implements WorkflowAccessPolicy
{
    public function allows(WorkflowContext $context, string $id, string $version): bool
    {
        $accounts = (array) config('x-change-workflows.accounts', []);
        $grants = $accounts[$context->accountId] ?? [];

        return $context->actorId === $context->accountId
            && is_array($grants)
            && in_array($id.'@'.$version, $grants, true);
    }
}
