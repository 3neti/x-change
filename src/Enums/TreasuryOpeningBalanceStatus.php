<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Enums;

enum TreasuryOpeningBalanceStatus: string
{
    case Reconciled = 'reconciled';
    case Recognized = 'recognized';
    case ReviewRequired = 'review_required';
}
