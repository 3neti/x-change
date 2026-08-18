<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Keepsake;

use Carbon\CarbonImmutable;
use LBHurtado\XJournal\Data\ExecutionActorData;
use LBHurtado\XJournal\Data\ExecutionJournalEntryData;
use LBHurtado\XJournal\Data\ExecutionReferenceData;
use LBHurtado\XJournal\Data\ExecutionSubjectData;
use LBHurtado\XJournal\Services\ExecutionJournalRecorder;

final readonly class InstanceKeepsakeJournal
{
    public function __construct(private ExecutionJournalRecorder $journal) {}

    /** @param array<string, int> $counts */
    public function exported(
        string $exportReference,
        string $authorizationReference,
        string $planHash,
        string $manifestHash,
        string $archiveHash,
        array $counts,
    ): void {
        $this->journal->record(new ExecutionJournalEntryData(
            eventType: 'instance.keepsake.exported',
            occurredAt: CarbonImmutable::now(),
            actor: new ExecutionActorData(id: 'artisan-console', type: 'console'),
            subject: new ExecutionSubjectData(
                id: $exportReference,
                type: 'instance_keepsake',
                display: 'Encrypted instance keepsake',
            ),
            references: new ExecutionReferenceData(
                correlationId: 'instance-keepsake:'.$exportReference,
                executionId: $exportReference,
                metadata: ['authorization_reference' => $authorizationReference],
            ),
            idempotencyKey: 'x-change:instance-keepsake-exported:'.$exportReference,
            payload: [
                'plan_hash' => $planHash,
                'manifest_hash' => $manifestHash,
                'archive_hash' => $archiveHash,
                'counts' => $counts,
                'binary_payload_persisted_in_journal' => false,
            ],
            metadata: [
                'schema' => 'x-change.instance-keepsake-export-journal.v1',
                'sensitive_access' => true,
                'provider_calls' => false,
                'moves_money' => false,
                'restores_financial_state' => false,
            ],
        ));
    }
}
