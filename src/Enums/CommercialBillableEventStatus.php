<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Enums;

enum CommercialBillableEventStatus: string
{
    case Received = 'received';
    case Posted = 'posted';
    case Reversed = 'reversed';
}
