<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Settlement;

use Spatie\LaravelData\Data;

class SettlementEnvelopeReadinessData extends Data
{
    /**
     * @param  array<int, string>  $missing
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public bool $required,
        public bool $exists,
        public bool $ready,
        public array $missing = [],
        public array $meta = [],
    ) {}

    public static function notAvailable(bool $required = true): self
    {
        return new self(
            required: $required,
            exists: false,
            ready: false,
            missing: $required ? ['settlement_envelope'] : [],
            meta: [
                'driver' => 'null',
            ],
        );
    }
}
