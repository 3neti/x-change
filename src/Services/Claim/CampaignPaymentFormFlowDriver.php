<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Claim;

use LBHurtado\FormFlowManager\Services\DriverService;
use Symfony\Component\Yaml\Yaml;

final class CampaignPaymentFormFlowDriver extends DriverService
{
    public function loadConfig(string $driverName = 'campaign-payment-completion'): void
    {
        $this->config = Yaml::parseFile(
            dirname(__DIR__, 3).'/config/form-flow-drivers/campaign-payment-completion.yaml',
        );
    }
}
