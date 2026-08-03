<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Commercial;

use InvalidArgumentException;
use Spatie\LaravelData\Data;

final class ProviderCostEvidenceData extends Data
{
    /**
     * @param  array<string, scalar|null>  $metadata
     */
    public function __construct(
        public readonly string $commercialSaleReference,
        public readonly string $provider,
        public readonly string $connectionReference,
        public readonly string $evidenceType,
        public readonly string $evidenceReference,
        public readonly bool $cashMovementObserved,
        public readonly int $observedAmountMinor,
        public readonly string $currency,
        public readonly string $observedAt,
        public readonly string $idempotencyKey,
        public readonly array $metadata = [],
    ) {
        foreach ([
            'commercial sale reference' => $this->commercialSaleReference,
            'provider' => $this->provider,
            'connection reference' => $this->connectionReference,
            'evidence type' => $this->evidenceType,
            'evidence reference' => $this->evidenceReference,
            'observed timestamp' => $this->observedAt,
            'idempotency key' => $this->idempotencyKey,
        ] as $field => $value) {
            if (trim($value) === '') {
                throw new InvalidArgumentException("Provider cost {$field} is required.");
            }
        }

        if ($this->observedAmountMinor < 0) {
            throw new InvalidArgumentException('Observed provider cost cannot be negative.');
        }

        if (preg_match('/^[A-Z]{3}$/', mb_strtoupper($this->currency)) !== 1) {
            throw new InvalidArgumentException('Provider cost currency is invalid.');
        }
    }
}
