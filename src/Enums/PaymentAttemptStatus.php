<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Enums;

enum PaymentAttemptStatus: string
{
    case PendingInstructions = 'pending_instructions';
    case AwaitingPayment = 'awaiting_payment';
    case Verifying = 'verifying';
    case Verified = 'verified';
    case Settled = 'settled';
    case Suspense = 'suspense';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
}
