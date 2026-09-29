<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Funding;

use LBHurtado\XChange\Enums\OnDemandIssuanceFundingBasis;

final readonly class OnDemandIssuanceFundingRequirementData
{
    public function __construct(
        public OnDemandIssuanceFundingBasis $basis,
        public int $requiredAmountMinor,
        public int $reservedClientFundsMinor,
        public int $externalAmountMinor,
    ) {}
}
