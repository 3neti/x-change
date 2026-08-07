<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Enums;

enum PartnerCommissionPayoutBatchStatus: string
{
    case AwaitingApproval = 'awaiting_approval';
    case Approved = 'approved';
    case Submitted = 'submitted';
    case Pending = 'pending';
    case Settled = 'settled';
    case Rejected = 'rejected';
    case Suspense = 'suspense';
}
