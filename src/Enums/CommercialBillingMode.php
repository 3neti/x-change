<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Enums;

enum CommercialBillingMode: string
{
    case Informational = 'informational';
    case Billable = 'billable';
}
