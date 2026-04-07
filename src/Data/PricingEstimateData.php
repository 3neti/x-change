<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data;

use Spatie\LaravelData\Data;

class PricingEstimateData extends Data
{
    /**
     * @param  array<string, mixed>  $components
     */
    public function __construct(
        public string $currency,
        public float $base_fee,
        public array $components,
        public float $total,
    ) {}
}
