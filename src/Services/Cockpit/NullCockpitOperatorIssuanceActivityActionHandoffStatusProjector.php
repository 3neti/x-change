<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

use LBHurtado\XChange\Contracts\CockpitOperatorIssuanceActivityActionHandoffStatusProjectorContract;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityActionHandoffResultData;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityActionHandoffStatusProjectionData;

class NullCockpitOperatorIssuanceActivityActionHandoffStatusProjector implements CockpitOperatorIssuanceActivityActionHandoffStatusProjectorContract
{
    public function project(CockpitOperatorIssuanceActivityActionHandoffResultData $result): CockpitOperatorIssuanceActivityActionHandoffStatusProjectionData
    {
        return new CockpitOperatorIssuanceActivityActionHandoffStatusProjectionData(
            activity_id: $result->activity_id,
            correlation_id: $result->correlation_id,
            action_handoff_status: $result->status,
            action_hint_id: $result->action_hint_id,
            action_run_id: $result->action_run_id,
            metadata: [
                'action_handoff' => [
                    'status' => $result->status,
                    'action_hint_id' => $result->action_hint_id,
                    'action_run_id' => $result->action_run_id,
                    'executes_action' => $result->executes_action,
                ],
            ],
        );
    }
}
