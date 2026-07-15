<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data;

use Spatie\LaravelData\Data;

class MinimumWithdrawalPolicyData extends Data
{
    public function __construct(
        public string $currency,
        public float $issuer_default_minimum,
        public ?float $provider_minimum,
        public ?float $rail_minimum,
        public ?float $operator_minimum,
        public float $effective_minimum,
        public string $source,
        public ?string $provider,
        public ?string $settlement_rail,
    ) {}

    public function floorWithoutOperator(): float
    {
        return max(array_filter([
            $this->issuer_default_minimum,
            $this->provider_minimum,
            $this->rail_minimum,
        ], static fn (?float $value): bool => $value !== null));
    }
}
