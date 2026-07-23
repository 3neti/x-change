<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Enums;

enum FundingVerificationTrigger: string
{
    case Webhook = 'webhook';
    case Operator = 'operator';
    case Schedule = 'schedule';
}
