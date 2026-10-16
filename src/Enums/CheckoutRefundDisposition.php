<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Enums;

enum CheckoutRefundDisposition: string
{
    case ExternalReturn = 'external_return';
    case PayCode = 'pay_code';
}
