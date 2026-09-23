<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Settlement;

use Carbon\CarbonImmutable;
use SensitiveParameter;

final readonly class PolicyCompletionPreparationData
{
    /**
     * @param  array<string, mixed>  $applicantEvidence
     */
    public function __construct(
        public string $driverId,
        public string $driverVersion,
        public string $idempotencyKey,
        public string $fingerprint,
        public string $projectionReference,
        public string $envelopeReference,
        public int $envelopePayloadVersion,
        public string $coverageReference,
        public string $coverageType,
        public ?int $coverageAmountMinor,
        public string $currency,
        public CarbonImmutable $coverageEffectiveAt,
        public ?CarbonImmutable $coverageExpiresAt,
        public string $paymentRecognitionReference,
        public int $paymentAmountMinor,
        public CarbonImmutable $paymentSettledAt,
        public string $completionPayCodeReference,
        public int $claimNumber,
        #[SensitiveParameter]
        private array $applicantEvidence,
    ) {}

    /** @return array<string, mixed> */
    public function privateApplicantEvidence(): array
    {
        return $this->applicantEvidence;
    }

    /** @return array<string, int|string|null> */
    public function safeContext(): array
    {
        return [
            'driver_id' => $this->driverId,
            'driver_version' => $this->driverVersion,
            'idempotency_key' => $this->idempotencyKey,
            'fingerprint' => $this->fingerprint,
            'projection_reference' => $this->projectionReference,
            'envelope_reference' => $this->envelopeReference,
            'envelope_payload_version' => $this->envelopePayloadVersion,
            'coverage_reference' => $this->coverageReference,
            'coverage_type' => $this->coverageType,
            'coverage_amount_minor' => $this->coverageAmountMinor,
            'currency' => $this->currency,
            'payment_recognition_reference' => $this->paymentRecognitionReference,
            'payment_amount_minor' => $this->paymentAmountMinor,
            'completion_pay_code_reference' => $this->completionPayCodeReference,
            'claim_number' => $this->claimNumber,
            'applicant_evidence_count' => count($this->applicantEvidence),
        ];
    }
}
