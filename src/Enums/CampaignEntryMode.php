<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Enums;

enum CampaignEntryMode: string
{
    case PayCodeOnOpen = 'pay_code_on_open';
    case ReusablePaymentQr = 'reusable_payment_qr';
}
