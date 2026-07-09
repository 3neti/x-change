<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Cockpit;

use Spatie\LaravelData\Data;

class CockpitOperatorIssuanceActivityJournalHandoffResultData extends Data
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly string $schema = 'x-change.cockpit.operator-issuance-activity-journal-handoff.v1',
        public readonly string $status = 'not_wired',
        public readonly ?string $activity_id = null,
        public readonly ?string $correlation_id = null,
        public readonly ?string $journal_entry_id = null,
        public readonly bool $writes_journal = false,
        public readonly string $source = 'null-cockpit-operator-issuance-activity-journal-handoff',
        public readonly string $reason = 'x-journal handoff is not wired. Cockpit activity remains operational evidence only.',
        public readonly array $metadata = [],
    ) {}
}
