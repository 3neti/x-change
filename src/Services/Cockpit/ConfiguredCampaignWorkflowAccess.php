<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

use LBHurtado\SettlementEnvelope\Contracts\WorkflowAccessPolicy;
use LBHurtado\SettlementEnvelope\Data\WorkflowContext;

final class ConfiguredCampaignWorkflowAccess implements WorkflowAccessPolicy
{
    public function allows(WorkflowContext $context, string $id, string $version): bool
    {
        return config('x-change-workflows.enabled', true) === true
            && $context->actorId === $context->accountId;
    }
}
