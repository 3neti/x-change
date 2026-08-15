<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Enums;

enum TreasuryReconciliationRunStatus: string
{
    case AwaitingApproval = 'awaiting_approval';
    case Approved = 'approved';
    case Completed = 'completed';
    case ReviewRequired = 'review_required';
    case Failed = 'failed';
}
