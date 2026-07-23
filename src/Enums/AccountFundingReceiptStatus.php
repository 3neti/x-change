<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Enums;

enum AccountFundingReceiptStatus: string
{
    case Observed = 'observed';
    case AwaitingApproval = 'awaiting_approval';
    case Ready = 'ready';
    case Settled = 'settled';
    case Suspense = 'suspense';
    case Reversed = 'reversed';
}
