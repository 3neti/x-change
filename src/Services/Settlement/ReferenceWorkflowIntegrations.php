<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Settlement;

use LBHurtado\SettlementEnvelope\Contracts\WorkflowCatalog;
use LBHurtado\SettlementEnvelope\Services\WorkflowIntegrationRegistry;

/** Explicit construction only; installing x-change does not activate integrations. */
final readonly class ReferenceWorkflowIntegrations
{
    public function __construct(
        private WorkflowCatalog $catalog,
        private AuiDemonstrationWorkflowAdapter $aui,
        private PhilhealthBstDemoWorkflowAdapter $bst,
    ) {}

    public function registry(): WorkflowIntegrationRegistry
    {
        return new WorkflowIntegrationRegistry($this->catalog, [$this->aui, $this->bst]);
    }
}
