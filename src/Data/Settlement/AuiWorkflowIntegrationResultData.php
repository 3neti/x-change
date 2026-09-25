<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Settlement;

use LBHurtado\SettlementEnvelope\Contracts\WorkflowIntegrationResult;
use LBHurtado\SettlementEnvelope\Enums\WorkflowIntegrationStatus;

final readonly class AuiWorkflowIntegrationResultData implements WorkflowIntegrationResult
{
    public function __construct(private AuiDemonstrationPolicyResponseData $response) {}

    public function reference(): string
    {
        return $this->response->policyReference;
    }

    public function status(): WorkflowIntegrationStatus
    {
        return WorkflowIntegrationStatus::Completed;
    }

    public function demonstrationOnly(): bool
    {
        return true;
    }

    public function response(): AuiDemonstrationPolicyResponseData
    {
        return $this->response;
    }
}
