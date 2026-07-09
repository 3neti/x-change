<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

use LBHurtado\XChange\Contracts\CockpitOperatorIssuanceActivityRecorderContract;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityItemData;

class NullCockpitOperatorIssuanceActivityRecorder implements CockpitOperatorIssuanceActivityRecorderContract
{
    public function record(CockpitOperatorIssuanceActivityItemData $activity): void
    {
        //
    }
}
