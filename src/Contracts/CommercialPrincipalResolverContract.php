<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\XChange\Models\CommercialPrincipal;

interface CommercialPrincipalResolverContract
{
    public function resolve(): CommercialPrincipal;
}
