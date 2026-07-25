<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Enums;

enum PayCodeSettlementDestination: string
{
    case ProviderPayout = 'provider_payout';
    case AccountFunding = 'account_funding';
}
