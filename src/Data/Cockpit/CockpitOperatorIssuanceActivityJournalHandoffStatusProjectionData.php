<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Cockpit;

use Spatie\LaravelData\Data;

class CockpitOperatorIssuanceActivityJournalHandoffStatusProjectionData extends Data
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly string $schema = 'x-change.cockpit.operator-issuance-activity-journal-handoff-status-projection.v1',
        public readonly string $status = 'not_persisted',
        public readonly ?string $activity_id = null,
        public readonly ?string $correlation_id = null,
        public readonly string $journal_handoff_status = 'not_wired',
        public readonly ?string $journal_entry_id = null,
        public readonly bool $persists_status = false,
        public readonly string $source = 'null-cockpit-operator-issuance-activity-journal-handoff-status-projector',
        public readonly string $reason = 'Journal handoff status projection is not wired. Durable activity status remains unchanged.',
        public readonly array $metadata = [],
    ) {}
}
