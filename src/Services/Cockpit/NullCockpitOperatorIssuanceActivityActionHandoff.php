<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

use LBHurtado\XChange\Contracts\CockpitOperatorIssuanceActivityActionHandoffContract;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityActionHandoffResultData;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityItemData;

class NullCockpitOperatorIssuanceActivityActionHandoff implements CockpitOperatorIssuanceActivityActionHandoffContract
{
    public function handoff(CockpitOperatorIssuanceActivityItemData $activity): CockpitOperatorIssuanceActivityActionHandoffResultData
    {
        return new CockpitOperatorIssuanceActivityActionHandoffResultData(
            activity_id: $activity->id,
            correlation_id: $activity->correlation_id,
        );
    }
}
