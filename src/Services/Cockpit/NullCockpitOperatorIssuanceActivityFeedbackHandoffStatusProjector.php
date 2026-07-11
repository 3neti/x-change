<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

use LBHurtado\XChange\Contracts\CockpitOperatorIssuanceActivityFeedbackHandoffStatusProjectorContract;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityFeedbackHandoffResultData;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityFeedbackHandoffStatusProjectionData;

class NullCockpitOperatorIssuanceActivityFeedbackHandoffStatusProjector implements CockpitOperatorIssuanceActivityFeedbackHandoffStatusProjectorContract
{
    public function project(CockpitOperatorIssuanceActivityFeedbackHandoffResultData $result): CockpitOperatorIssuanceActivityFeedbackHandoffStatusProjectionData
    {
        return new CockpitOperatorIssuanceActivityFeedbackHandoffStatusProjectionData(
            activity_id: $result->activity_id,
            correlation_id: $result->correlation_id,
            feedback_handoff_status: $result->status,
            feedback_intent_id: $result->feedback_intent_id,
            delivery_plan_id: $result->delivery_plan_id,
            delivery_receipt_id: $result->delivery_receipt_id,
        );
    }
}
