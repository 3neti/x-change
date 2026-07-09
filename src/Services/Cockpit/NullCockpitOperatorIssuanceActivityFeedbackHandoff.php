<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

use LBHurtado\XChange\Contracts\CockpitOperatorIssuanceActivityFeedbackHandoffContract;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityFeedbackHandoffResultData;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityItemData;

class NullCockpitOperatorIssuanceActivityFeedbackHandoff implements CockpitOperatorIssuanceActivityFeedbackHandoffContract
{
    public function handoff(CockpitOperatorIssuanceActivityItemData $activity): CockpitOperatorIssuanceActivityFeedbackHandoffResultData
    {
        return new CockpitOperatorIssuanceActivityFeedbackHandoffResultData(
            activity_id: $activity->id,
            correlation_id: $activity->correlation_id,
        );
    }
}
