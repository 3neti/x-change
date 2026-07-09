<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

use LBHurtado\XChange\Contracts\CockpitOperatorIssuanceActivityJournalHandoffContract;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityItemData;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityJournalHandoffResultData;

class NullCockpitOperatorIssuanceActivityJournalHandoff implements CockpitOperatorIssuanceActivityJournalHandoffContract
{
    public function handoff(CockpitOperatorIssuanceActivityItemData $activity): CockpitOperatorIssuanceActivityJournalHandoffResultData
    {
        return new CockpitOperatorIssuanceActivityJournalHandoffResultData(
            activity_id: $activity->id,
            correlation_id: $activity->correlation_id,
        );
    }
}
