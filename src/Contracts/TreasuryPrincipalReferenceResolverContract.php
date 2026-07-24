<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use Illuminate\Database\Eloquent\Model;

interface TreasuryPrincipalReferenceResolverContract
{
    public function resolve(Model $principal): string;
}
