<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\XChange\Data\Treasury\TreasuryProviderConnectionData;

interface CommercialSettlementAccountResolverContract
{
    public function resolveClientFundsPosition(
        string $accountReference,
        string $principalReference,
        TreasuryProviderConnectionData $connection,
    ): string;
}
