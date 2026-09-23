<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Settlement;

final readonly class ProvisionalCoverageBoundData
{
    public function __construct(
        public string $coverageReference,
        public string $envelopeReference,
        public string $recognitionReference,
        public string $campaignReference,
        public string $campaignRevisionId,
        public string $bindingReference,
        public string $driverId,
        public string $driverVersion,
        public string $coverageType,
        public ?int $coverageAmountMinor,
        public string $currency,
        public string $effectiveAt,
        public ?string $expiresAt,
    ) {}

    /** @return array<string, int|string|null> */
    public function toBroadcastArray(): array
    {
        return [
            'schema' => 'x-change.provisional-coverage-bound.v1',
            'coverage_reference' => $this->coverageReference,
            'envelope_reference' => $this->envelopeReference,
            'recognition_reference' => $this->recognitionReference,
            'campaign_reference' => $this->campaignReference,
            'campaign_revision_id' => $this->campaignRevisionId,
            'binding_reference' => $this->bindingReference,
            'driver_id' => $this->driverId,
            'driver_version' => $this->driverVersion,
            'coverage_type' => $this->coverageType,
            'coverage_amount_minor' => $this->coverageAmountMinor,
            'currency' => $this->currency,
            'effective_at' => $this->effectiveAt,
            'expires_at' => $this->expiresAt,
        ];
    }
}
