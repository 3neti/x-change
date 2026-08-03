<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use Illuminate\Database\Eloquent\Model;

interface CommercialPartnerResolverContract
{
    public function resolve(string $partnerReference): ?Model;
}
