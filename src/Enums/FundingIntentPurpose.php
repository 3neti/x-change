<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Enums;

enum FundingIntentPurpose: string
{
    case AccountFunding = 'account_funding';
    case OnDemandIssuance = 'on_demand_issuance';
}
