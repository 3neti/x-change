<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\XCommerce\Data\CommercialOfferingData;

interface CommercialOfferingResolverContract
{
    public function resolve(string $profile): CommercialOfferingData;
}
