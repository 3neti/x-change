<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Settlement;

final readonly class PolicyCompletionTransportReadinessData
{
    /**
     * @param  list<string>  $missingFields
     */
    public function __construct(
        public bool $ready,
        public string $status,
        public string $reason,
        public string $driverId,
        public string $driverVersion,
        public array $missingFields = [],
        public ?string $dispositionFingerprint = null,
    ) {}

    /** @return array<string, bool|list<string>|string|null> */
    public function toSafeArray(): array
    {
        return [
            'ready' => $this->ready,
            'status' => $this->status,
            'reason' => $this->reason,
            'driver_id' => $this->driverId,
            'driver_version' => $this->driverVersion,
            'missing_fields' => $this->missingFields,
            'disposition_fingerprint' => $this->dispositionFingerprint,
        ];
    }
}
