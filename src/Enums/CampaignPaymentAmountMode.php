<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Enums;

enum CampaignPaymentAmountMode: string
{
    case Open = 'open';
    case Fixed = 'fixed';
}
