<?php

namespace LBHurtado\MoneyIssuer\Data;

use Spatie\LaravelData\Data;

class BalanceData extends Data
{
    public function __construct(
        public string $account,
        public float $balance,
        public string $currency,
        public ?string $retrieved_at = null,
        public array $meta = [],
    ) {}
}
