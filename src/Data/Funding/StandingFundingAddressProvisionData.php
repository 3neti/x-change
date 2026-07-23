<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Funding;

use LBHurtado\EmiCore\Data\Funding\StandingFundingAddressData;
use LBHurtado\XChange\Models\StandingFundingAddress;

final class StandingFundingAddressProvisionData
{
    public function __construct(
        public readonly StandingFundingAddress $address,
        public readonly StandingFundingAddressData $providerAddress,
    ) {}
}
