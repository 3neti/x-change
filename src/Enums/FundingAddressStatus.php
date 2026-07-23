<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Enums;

enum FundingAddressStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Retired = 'retired';
}
