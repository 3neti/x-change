<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Affiliation;

use Carbon\CarbonImmutable;
use LBHurtado\XAffiliation\Contracts\AffiliationEventSinkContract;
use LBHurtado\XAffiliation\Models\AffiliationEvent;
use LBHurtado\XJournal\Data\ExecutionActorData;
use LBHurtado\XJournal\Data\ExecutionJournalEntryData;
use LBHurtado\XJournal\Data\ExecutionReferenceData;
use LBHurtado\XJournal\Data\ExecutionSubjectData;
use LBHurtado\XJournal\Services\ExecutionJournalRecorder;

final readonly class XJournalAffiliationEventSink implements AffiliationEventSinkContract
{
    public function __construct(private ExecutionJournalRecorder $journal) {}

    public function record(AffiliationEvent $event): void
    {
        $network = $event->network()->firstOrFail();

        $this->journal->record(new ExecutionJournalEntryData(
            eventType: $event->event_type,
            occurredAt: CarbonImmutable::parse($event->occurred_at),
            actor: new ExecutionActorData(
                id: $event->authority_reference,
                type: $event->authority_type ?? 'x-change-affiliation',
            ),
            subject: new ExecutionSubjectData(
                id: $event->subject_reference ?? $event->reference,
                type: $event->subject_type ?? 'affiliation_network',
                display: $event->reference,
            ),
            references: new ExecutionReferenceData(
                correlationId: 'affiliation-network:'.$network->reference,
                causationId: $event->authority_reference,
                executionId: $event->reference,
                externalReference: $event->reference,
                metadata: [
                    'network_reference' => $network->reference,
                    'facts_hash' => $event->facts_hash,
                ],
            ),
            idempotencyKey: 'x-change:affiliation:'.$event->reference,
            payload: [
                'affiliation_event_reference' => $event->reference,
                'facts_hash' => $event->facts_hash,
            ],
            metadata: [
                'schema' => 'x-change.affiliation-journal.v1',
                'domain' => 'affiliation',
                'contains_raw_mobile' => false,
                'financial_effect' => false,
                'authorization_effect' => false,
            ],
        ));
    }
}
