<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Enums;

enum OnDemandIssuanceFundingBasis: string
{
    case FullAmount = 'full_amount';
    case Shortfall = 'shortfall';
}
