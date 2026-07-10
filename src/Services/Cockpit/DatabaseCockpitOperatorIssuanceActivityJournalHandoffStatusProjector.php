<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

use LBHurtado\XChange\Contracts\CockpitOperatorIssuanceActivityJournalHandoffStatusProjectorContract;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityJournalHandoffResultData;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityJournalHandoffStatusProjectionData;
use LBHurtado\XChange\Models\CockpitOperatorIssuanceActivity;

class DatabaseCockpitOperatorIssuanceActivityJournalHandoffStatusProjector implements CockpitOperatorIssuanceActivityJournalHandoffStatusProjectorContract
{
    public function project(CockpitOperatorIssuanceActivityJournalHandoffResultData $result): CockpitOperatorIssuanceActivityJournalHandoffStatusProjectionData
    {
        if ($result->activity_id === null || trim($result->activity_id) === '') {
            return $this->notPersisted($result, 'missing_activity_id', 'Journal handoff status was not persisted because the handoff result has no activity ID.');
        }

        $activity = CockpitOperatorIssuanceActivity::query()
            ->where('activity_id', $result->activity_id)
            ->first();

        if (! $activity instanceof CockpitOperatorIssuanceActivity) {
            return $this->notPersisted($result, 'missing_activity', 'Journal handoff status was not persisted because the durable activity row was not found.');
        }

        $activity->forceFill([
            'journal_handoff_status' => $result->status,
            'metadata' => [
                ...($activity->metadata ?? []),
                'journal_handoff' => $this->journalHandoffMetadata($result),
            ],
        ])->save();

        return new CockpitOperatorIssuanceActivityJournalHandoffStatusProjectionData(
            status: 'persisted',
            activity_id: $result->activity_id,
            correlation_id: $result->correlation_id,
            journal_handoff_status: $result->status,
            journal_entry_id: $result->journal_entry_id,
            persists_status: true,
            source: 'database-cockpit-operator-issuance-activity-journal-handoff-status-projector',
            reason: 'Journal handoff status was persisted to the durable Cockpit activity row.',
            metadata: [
                'journal_handoff' => $this->journalHandoffMetadata($result),
            ],
        );
    }

    private function notPersisted(
        CockpitOperatorIssuanceActivityJournalHandoffResultData $result,
        string $status,
        string $reason,
    ): CockpitOperatorIssuanceActivityJournalHandoffStatusProjectionData {
        return new CockpitOperatorIssuanceActivityJournalHandoffStatusProjectionData(
            status: $status,
            activity_id: $result->activity_id,
            correlation_id: $result->correlation_id,
            journal_handoff_status: $result->status,
            journal_entry_id: $result->journal_entry_id,
            persists_status: false,
            source: 'database-cockpit-operator-issuance-activity-journal-handoff-status-projector',
            reason: $reason,
            metadata: [
                'journal_handoff' => $this->journalHandoffMetadata($result),
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function journalHandoffMetadata(CockpitOperatorIssuanceActivityJournalHandoffResultData $result): array
    {
        return [
            'status' => $result->status,
            'journal_entry_id' => $result->journal_entry_id,
            'writes_journal' => $result->writes_journal,
            'source' => $result->source,
            'reason' => $result->reason,
            'metadata' => $this->safeMetadata($result->metadata),
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function safeMetadata(array $metadata): array
    {
        return array_intersect_key($metadata, array_flip([
            'reference_number',
            'event_type',
            'idempotency_key',
            'exception',
        ]));
    }
}
