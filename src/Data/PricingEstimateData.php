<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data;

use Spatie\LaravelData\Data;

class PricingEstimateData extends Data
{
    /**
     * @param  array<string, float|int>  $components
     * @param  array<int, array<string, mixed>>  $charges
     */
    public function __construct(
        public string $currency = 'PHP',
        public float $base_fee = 0.0,
        public array $components = [],
        public float $total = 0.0,
        public array $charges = [],
    ) {}
}
