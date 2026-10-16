<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Enums;

enum CheckoutRefundStatus: string
{
    case Open = 'open';
    case Held = 'held';
    case Eligible = 'eligible';
    case PayCodeIssued = 'pay_code_issued';
    case Redeemed = 'redeemed';
    case Expired = 'expired';
    case ManualRecovery = 'manual_recovery';
    case ExternalReturnRecorded = 'external_return_recorded';
    case Reconciled = 'reconciled';
}
