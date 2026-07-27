<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Enums;

enum FundingTransferAmountReservationStatus: string
{
    case Reserved = 'reserved';
    case Matched = 'matched';
    case Credited = 'credited';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
}
