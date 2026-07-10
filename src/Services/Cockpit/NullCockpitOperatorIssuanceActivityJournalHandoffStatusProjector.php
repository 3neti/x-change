<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

use LBHurtado\XChange\Contracts\CockpitOperatorIssuanceActivityJournalHandoffStatusProjectorContract;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityJournalHandoffResultData;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityJournalHandoffStatusProjectionData;

class NullCockpitOperatorIssuanceActivityJournalHandoffStatusProjector implements CockpitOperatorIssuanceActivityJournalHandoffStatusProjectorContract
{
    public function project(CockpitOperatorIssuanceActivityJournalHandoffResultData $result): CockpitOperatorIssuanceActivityJournalHandoffStatusProjectionData
    {
        return new CockpitOperatorIssuanceActivityJournalHandoffStatusProjectionData(
            activity_id: $result->activity_id,
            correlation_id: $result->correlation_id,
            journal_handoff_status: $result->status,
            journal_entry_id: $result->journal_entry_id,
            metadata: [
                'handoff_source' => $result->source,
                'handoff_writes_journal' => $result->writes_journal,
                'handoff_reason' => $result->reason,
                'handoff_metadata' => $result->metadata,
            ],
        );
    }
}
