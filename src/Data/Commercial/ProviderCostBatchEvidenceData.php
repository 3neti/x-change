<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Commercial;

use InvalidArgumentException;
use Spatie\LaravelData\Data;

final class ProviderCostBatchEvidenceData extends Data
{
    /**
     * @param  array<string, scalar|null>  $metadata
     */
    public function __construct(
        public readonly string $reference,
        public readonly string $provider,
        public readonly string $connectionReference,
        public readonly string $currency,
        public readonly string $evidenceType,
        public readonly string $evidenceReference,
        public readonly int $observedAmountMinor,
        public readonly string $periodStartedAt,
        public readonly string $periodEndedAt,
        public readonly string $observedAt,
        public readonly string $idempotencyKey,
        public readonly array $metadata = [],
    ) {
        foreach ([
            'reference' => $this->reference,
            'provider' => $this->provider,
            'connection reference' => $this->connectionReference,
            'currency' => $this->currency,
            'evidence type' => $this->evidenceType,
            'evidence reference' => $this->evidenceReference,
            'period start' => $this->periodStartedAt,
            'period end' => $this->periodEndedAt,
            'observed timestamp' => $this->observedAt,
            'idempotency key' => $this->idempotencyKey,
        ] as $field => $value) {
            if (trim($value) === '') {
                throw new InvalidArgumentException("Provider cost batch {$field} is required.");
            }
        }

        if ($this->observedAmountMinor < 0) {
            throw new InvalidArgumentException('Observed provider cost cannot be negative.');
        }

        if (preg_match('/^[A-Z]{3}$/', mb_strtoupper($this->currency)) !== 1) {
            throw new InvalidArgumentException('Provider cost batch currency is invalid.');
        }

        if (strtotime($this->periodStartedAt) === false
            || strtotime($this->periodEndedAt) === false
            || strtotime($this->observedAt) === false
            || strtotime($this->periodStartedAt) > strtotime($this->periodEndedAt)) {
            throw new InvalidArgumentException('Provider cost batch period is invalid.');
        }
    }
}
