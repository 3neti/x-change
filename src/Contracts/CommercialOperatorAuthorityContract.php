<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use Illuminate\Database\Eloquent\Model;
use LBHurtado\XChange\Enums\CommercialOperatorCapability;

interface CommercialOperatorAuthorityContract
{
    public function allows(Model $operator, CommercialOperatorCapability $capability): bool;
}
