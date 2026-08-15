<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Treasury;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use LBHurtado\XChange\Models\TreasuryReconciliationRun;
use LBHurtado\XJournal\Data\ExecutionActorData;
use LBHurtado\XJournal\Data\ExecutionJournalEntryData;
use LBHurtado\XJournal\Data\ExecutionReferenceData;
use LBHurtado\XJournal\Data\ExecutionSubjectData;
use LBHurtado\XJournal\Services\ExecutionJournalRecorder;

final readonly class TreasuryReconciliationRunJournal
{
    public function __construct(private ExecutionJournalRecorder $recorder) {}

    public function record(TreasuryReconciliationRun $run, string $eventType, Model|string $actor): void
    {
        $this->recorder->record(new ExecutionJournalEntryData(
            eventType: $eventType,
            occurredAt: CarbonImmutable::parse($run->updated_at),
            actor: $actor instanceof Model
                ? new ExecutionActorData((string) $actor->getKey(), $actor->getMorphClass())
                : new ExecutionActorData($actor, 'system_principal'),
            subject: new ExecutionSubjectData(
                id: $run->reference,
                type: 'treasury_reconciliation_run',
                display: 'Provider Reconciliation Run',
            ),
            references: new ExecutionReferenceData(
                correlationId: 'treasury-reconciliation:'.$run->reference,
                causationId: $eventType,
                executionId: (string) $run->getKey(),
                externalReference: $run->evidence_reference,
                metadata: ['request_hash' => $run->request_hash],
            ),
            idempotencyKey: 'x-change:treasury-reconciliation:'.$eventType.':'.$run->getKey(),
            payload: [
                'status' => $run->status->value,
                'connection_reference' => $run->connection_reference,
                'provider' => $run->provider,
                'currency' => $run->currency,
                'provider_balance_minor' => $run->provider_balance_minor,
                'inventory_balance_minor' => $run->inventory_balance_minor,
                'position_balance_minor' => $run->position_balance_minor,
                'difference_minor' => $run->difference_minor,
                'evidence_reference' => $run->evidence_reference,
                'reason' => $run->reason,
            ],
            metadata: [
                'schema' => 'x-change.treasury-reconciliation-journal.v1',
                'domain' => 'treasury_reconciliation',
                'provider_call' => str_contains($eventType, 'executed')
                    || str_contains($eventType, 'review_required')
                    || str_contains($eventType, 'failed'),
            ],
        ));
    }
}
