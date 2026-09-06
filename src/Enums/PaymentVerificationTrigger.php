<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Enums;

enum PaymentVerificationTrigger: string
{
    case Payer = 'payer';
    case Schedule = 'schedule';
    case Webhook = 'webhook';
    case Operator = 'operator';
}
