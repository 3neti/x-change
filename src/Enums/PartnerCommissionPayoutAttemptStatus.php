<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Enums;

enum PartnerCommissionPayoutAttemptStatus: string
{
    case Submitting = 'submitting';
    case Pending = 'pending';
    case Settled = 'settled';
    case Rejected = 'rejected';
    case Suspense = 'suspense';
}
