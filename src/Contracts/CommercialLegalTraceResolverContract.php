<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\XCommerce\Data\CommercialOfferingData;

interface CommercialLegalTraceResolverContract
{
    public function forPublication(CommercialOfferingData $offering): CommercialOfferingData;
}
