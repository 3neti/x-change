<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Settlement;

use DomainException;
use LBHurtado\SettlementEnvelope\Contracts\WorkflowSubmission;
use SensitiveParameter;

final readonly class AuiWorkflowSubmissionData implements WorkflowSubmission
{
    public function __construct(#[SensitiveParameter] private PolicyCompletionPreparationData $preparation)
    {
        if (trim($preparation->idempotencyKey) === ''
            || strlen($preparation->idempotencyKey) > 255
            || preg_match('/[\x00-\x1f\x7f]/', $preparation->idempotencyKey)
            || preg_match('/\A[a-f0-9]{64}\z/', $preparation->fingerprint) !== 1) {
            throw new DomainException('A stable preparation identity is required.');
        }
    }

    public function workflowId(): string
    {
        return $this->preparation->driverId;
    }

    public function workflowVersion(): string
    {
        return $this->preparation->driverVersion;
    }

    public function idempotencyKey(): string
    {
        return $this->preparation->idempotencyKey;
    }

    public function fingerprint(): string
    {
        return $this->preparation->fingerprint;
    }

    public function preparation(): PolicyCompletionPreparationData
    {
        return $this->preparation;
    }
}
