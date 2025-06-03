<?php

namespace LBHurtado\MoneyIssuer\Data;

use Spatie\LaravelData\Data;

class TransferData extends Data
{
    public function __construct(
        public string $from_account,
        public string $to_account,
        public float $amount,
        public string $currency,
        public ?string $reference_id = null,
        public array $meta = [],
//        public bool $success,
//        public ?string $timestamp,
    ) {}
}
