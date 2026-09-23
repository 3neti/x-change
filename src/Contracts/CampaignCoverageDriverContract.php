<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\XChange\Data\Settlement\CampaignCoverageDecisionData;
use LBHurtado\XChange\Models\CampaignPaymentRecognition;

interface CampaignCoverageDriverContract
{
    public function driverId(): string;

    public function driverVersion(): string;

    /**
     * Resolve explicit coverage terms without persisting records or moving money.
     */
    public function decide(
        CampaignPaymentRecognition $recognition,
    ): CampaignCoverageDecisionData;
}
