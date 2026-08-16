<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\XCommerce\Data\CommercialComponentEconomicsSetData;

interface CommercialComponentEconomicsResolverContract
{
    public function resolve(string $profile): CommercialComponentEconomicsSetData;
}
