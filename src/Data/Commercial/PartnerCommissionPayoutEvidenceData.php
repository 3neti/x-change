<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Commercial;

use InvalidArgumentException;
use Spatie\LaravelData\Data;

final class PartnerCommissionPayoutEvidenceData extends Data
{
    /**
     * @param  array<string, scalar|null>  $metadata
     */
    public function __construct(
        public readonly string $evidenceReference,
        public readonly string $provider,
        public readonly string $connectionReference,
        public readonly int $amountMinor,
        public readonly string $currency,
        public readonly string $observedAt,
        public readonly string $idempotencyKey,
        public readonly array $metadata = [],
    ) {
        foreach ([
            'evidence reference' => $this->evidenceReference,
            'provider' => $this->provider,
            'connection reference' => $this->connectionReference,
            'observed timestamp' => $this->observedAt,
            'idempotency key' => $this->idempotencyKey,
        ] as $field => $value) {
            if (trim($value) === '') {
                throw new InvalidArgumentException("Partner commission {$field} is required.");
            }
        }

        if ($this->amountMinor <= 0) {
            throw new InvalidArgumentException('Partner commission payout amount must be positive.');
        }

        if (preg_match('/^[A-Z]{3}$/', mb_strtoupper($this->currency)) !== 1) {
            throw new InvalidArgumentException('Partner commission payout currency is invalid.');
        }
    }
}
