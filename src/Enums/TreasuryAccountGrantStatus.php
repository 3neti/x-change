<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Enums;

enum TreasuryAccountGrantStatus: string
{
    case AwaitingApproval = 'awaiting_approval';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Executed = 'executed';
}
