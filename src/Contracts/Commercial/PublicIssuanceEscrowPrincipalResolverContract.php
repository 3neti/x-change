<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts\Commercial;

use LBHurtado\XChange\Models\CommercialPrincipal;

interface PublicIssuanceEscrowPrincipalResolverContract
{
    public function resolve(): CommercialPrincipal;
}
