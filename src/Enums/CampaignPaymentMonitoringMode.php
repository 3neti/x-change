<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Enums;

enum CampaignPaymentMonitoringMode: string
{
    case Live = 'live';
    case Paused = 'paused';
}
