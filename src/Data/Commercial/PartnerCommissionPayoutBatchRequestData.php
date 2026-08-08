<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Commercial;

use InvalidArgumentException;
use Spatie\LaravelData\Data;

final class PartnerCommissionPayoutBatchRequestData extends Data
{
    /** @param array<string, scalar|null> $metadata */
    public function __construct(
        public readonly string $reference,
        public readonly string $partnerReference,
        public readonly string $provider,
        public readonly string $connectionReference,
        public readonly string $currency,
        public readonly string $periodStartedAt,
        public readonly string $periodEndedAt,
        public readonly string $idempotencyKey,
        public readonly array $metadata = [],
    ) {
        foreach ([
            'reference' => $this->reference,
            'partner reference' => $this->partnerReference,
            'provider' => $this->provider,
            'connection reference' => $this->connectionReference,
            'currency' => $this->currency,
            'period start' => $this->periodStartedAt,
            'period end' => $this->periodEndedAt,
            'idempotency key' => $this->idempotencyKey,
        ] as $field => $value) {
            if (trim($value) === '') {
                throw new InvalidArgumentException("Commission payout {$field} is required.");
            }
        }

        if (preg_match('/^[A-Z]{3}$/', mb_strtoupper($this->currency)) !== 1
            || strtotime($this->periodStartedAt) === false
            || strtotime($this->periodEndedAt) === false
            || strtotime($this->periodStartedAt) > strtotime($this->periodEndedAt)) {
            throw new InvalidArgumentException('Commission payout period or currency is invalid.');
        }
    }
}
