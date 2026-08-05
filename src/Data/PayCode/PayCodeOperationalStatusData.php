<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\PayCode;

use Spatie\LaravelData\Data;

class PayCodeOperationalStatusData extends Data
{
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $tone,
        public readonly string $availability_key,
        public readonly string $availability_label,
        public readonly string $settlement_outcome,
        public readonly bool $is_terminal,
        public readonly bool $can_claim,
        public readonly bool $can_retry_payout,
    ) {}
}
