<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Settlement;

final readonly class AuiDemonstrationPolicyTransportRequestData
{
    public const SCHEMA = 'x-change.aui-demonstration-policy-transport-request.v1';

    public function __construct(
        public PolicyCompletionPreparationData $preparation,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'schema' => self::SCHEMA,
            'demonstration_only' => true,
            'applicant_evidence_mode' => 'withheld',
            'idempotency_key' => $this->preparation->idempotencyKey,
            'preparation_fingerprint' => $this->preparation->fingerprint,
            'driver' => [
                'id' => $this->preparation->driverId,
                'version' => $this->preparation->driverVersion,
            ],
            'coverage' => [
                'reference' => $this->preparation->coverageReference,
                'type' => $this->preparation->coverageType,
                'amount_minor' => $this->preparation->coverageAmountMinor,
                'currency' => $this->preparation->currency,
                'effective_at' => $this->preparation->coverageEffectiveAt->toIso8601String(),
                'expires_at' => $this->preparation->coverageExpiresAt?->toIso8601String(),
            ],
            'payment' => [
                'recognition_reference' => $this->preparation->paymentRecognitionReference,
                'amount_minor' => $this->preparation->paymentAmountMinor,
                'settled_at' => $this->preparation->paymentSettledAt->toIso8601String(),
            ],
            'completion' => [
                'projection_reference' => $this->preparation->projectionReference,
                'pay_code_reference' => $this->preparation->completionPayCodeReference,
                'claim_number' => $this->preparation->claimNumber,
            ],
        ];
    }
}
