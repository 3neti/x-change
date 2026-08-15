<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Enums;

enum TreasuryOperatorCapability: string
{
    case ViewAccountGrants = 'treasury.account_grants.view';
    case RequestAccountGrants = 'treasury.account_grants.request';
    case ApproveAccountGrants = 'treasury.account_grants.approve';
    case ExecuteAccountGrants = 'treasury.account_grants.execute';
}
