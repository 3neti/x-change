<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Settlement;

use LBHurtado\XChange\Contracts\CampaignAutomaticDemonstrationResponderContract;
use LBHurtado\XChange\Data\Settlement\PolicyCompletionOutcomeData;
use LBHurtado\XChange\Data\Settlement\PolicyCompletionPreparationData;

final readonly class AuiAutomaticDemonstrationResponder implements CampaignAutomaticDemonstrationResponderContract
{
    public function __construct(
        private DispatchAuiDemonstrationPolicyViaPipedream $transport,
    ) {}

    public function driverId(): string
    {
        return AuiPersonalAccidentPolicyCompletionDriver::DRIVER_ID;
    }

    public function driverVersion(): string
    {
        return AuiPersonalAccidentPolicyCompletionDriver::DRIVER_VERSION;
    }

    public function complete(
        PolicyCompletionPreparationData $preparation,
    ): PolicyCompletionOutcomeData {
        return $this->transport->handle($preparation)->outcome();
    }
}
