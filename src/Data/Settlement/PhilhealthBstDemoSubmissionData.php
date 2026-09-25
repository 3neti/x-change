<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Settlement;

use LBHurtado\SettlementEnvelope\Contracts\WorkflowSubmission;
use SensitiveParameter;
use ThreeNeti\SettlementEnvelopePhilhealth\Data\PhilhealthBstDemoSubmissionData as IntegrationSubmission;

/** Compatibility boundary for the standalone synthetic intake contract. */
final readonly class PhilhealthBstDemoSubmissionData implements WorkflowSubmission
{
    private IntegrationSubmission $submission;

    /** @param array{claim_form: string, hospital_bill: string} $documentHashes */
    public function __construct(
        string $key,
        string $claimReference,
        int $requestedAmountMinor,
        #[SensitiveParameter] string $patientName,
        #[SensitiveParameter] string $patientMobile,
        #[SensitiveParameter] array $documentHashes,
    ) {
        $this->submission = new IntegrationSubmission($key, $claimReference, $requestedAmountMinor, $patientName, $patientMobile, $documentHashes);
    }

    public function integrationSubmission(): IntegrationSubmission
    {
        return $this->submission;
    }

    public function workflowId(): string
    {
        return $this->submission->workflowId();
    }

    public function workflowVersion(): string
    {
        return $this->submission->workflowVersion();
    }

    public function idempotencyKey(): string
    {
        return $this->submission->idempotencyKey();
    }

    public function fingerprint(): string
    {
        return $this->submission->fingerprint();
    }
}
