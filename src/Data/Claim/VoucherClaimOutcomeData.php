<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Claim;

use Spatie\LaravelData\Data;

final class VoucherClaimOutcomeData extends Data
{
    /**
     * @param  array<string, mixed>  $requirements
     */
    public function __construct(
        public readonly string $key,
        public readonly ?string $pricingProfile = null,
        public readonly array $requirements = [],
    ) {}
}
