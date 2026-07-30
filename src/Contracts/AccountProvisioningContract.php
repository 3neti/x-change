<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use Illuminate\Database\Eloquent\Model;
use LBHurtado\XChange\Data\Treasury\TreasuryAccountPortfolioData;

interface AccountProvisioningContract
{
    public function provision(Model $accountOwner): TreasuryAccountPortfolioData;
}
