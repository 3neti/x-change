<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Enums;

enum PublicAutoRegistrationStatus: string
{
    case AwaitingPayment = 'awaiting_payment';
    case PaymentSettled = 'payment_settled';
    case PinRequired = 'pin_required';
    case Active = 'active';
    case Failed = 'failed';
    case RefundHold = 'refund_hold';
}
