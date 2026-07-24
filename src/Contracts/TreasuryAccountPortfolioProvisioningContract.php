<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use Illuminate\Database\Eloquent\Model;
use LBHurtado\XChange\Data\Treasury\TreasuryAccountPortfolioData;

interface TreasuryAccountPortfolioProvisioningContract
{
    /**
     * @param  list<string>  $connectionReferences
     */
    public function provision(
        Model $accountOwner,
        array $connectionReferences = [],
    ): TreasuryAccountPortfolioData;
}
