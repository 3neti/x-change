<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\XChange\Data\Settlement\PolicyCompletionOutcomeData;
use LBHurtado\XChange\Data\Settlement\PolicyCompletionPreparationData;

interface CampaignAutomaticDemonstrationResponderContract
{
    public function driverId(): string;

    public function driverVersion(): string;

    public function complete(
        PolicyCompletionPreparationData $preparation,
    ): PolicyCompletionOutcomeData;
}
