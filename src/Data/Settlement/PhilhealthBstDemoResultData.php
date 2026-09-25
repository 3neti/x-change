<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Settlement;

use LBHurtado\SettlementEnvelope\Contracts\WorkflowIntegrationResult;
use LBHurtado\SettlementEnvelope\Enums\WorkflowIntegrationStatus;

final readonly class PhilhealthBstDemoResultData implements WorkflowIntegrationResult
{
    public function __construct(private string $submissionReference) {}

    public function reference(): string
    {
        return $this->submissionReference;
    }

    public function status(): WorkflowIntegrationStatus
    {
        return WorkflowIntegrationStatus::AwaitingReview;
    }

    public function demonstrationOnly(): bool
    {
        return true;
    }
}
