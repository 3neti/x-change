<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Treasury;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use LBHurtado\XChange\Models\TreasuryAccountGrant;
use LBHurtado\XJournal\Data\ExecutionActorData;
use LBHurtado\XJournal\Data\ExecutionJournalEntryData;
use LBHurtado\XJournal\Data\ExecutionReferenceData;
use LBHurtado\XJournal\Data\ExecutionSubjectData;
use LBHurtado\XJournal\Services\ExecutionJournalRecorder;

final readonly class TreasuryAccountGrantJournal
{
    public function __construct(private ExecutionJournalRecorder $recorder) {}

    public function record(TreasuryAccountGrant $grant, string $eventType, Model|string $actor): void
    {
        $this->recorder->record(new ExecutionJournalEntryData(
            eventType: $eventType,
            occurredAt: CarbonImmutable::parse($grant->updated_at),
            actor: $actor instanceof Model
                ? new ExecutionActorData((string) $actor->getKey(), $actor->getMorphClass())
                : new ExecutionActorData($actor, 'system_principal'),
            subject: new ExecutionSubjectData(
                id: $grant->reference,
                type: 'treasury_account_grant',
                display: 'Treasury Account Grant',
            ),
            references: new ExecutionReferenceData(
                correlationId: 'treasury-account-grant:'.$grant->reference,
                causationId: $eventType,
                executionId: (string) $grant->getKey(),
                externalReference: $grant->operation_reference ?? $grant->reference,
                metadata: ['request_hash' => $grant->request_hash],
            ),
            idempotencyKey: 'x-change:treasury-account-grant:'.$eventType.':'.$grant->getKey(),
            payload: [
                'status' => $grant->status->value,
                'amount_minor' => $grant->amount_minor,
                'currency' => $grant->currency,
                'connection_reference' => $grant->connection_reference,
                'test_allocation' => $grant->test_allocation,
                'source_position_reference' => $grant->source_position_reference,
                'destination_position_reference' => $grant->destination_position_reference,
                'operation_reference' => $grant->operation_reference,
            ],
            metadata: [
                'schema' => 'x-change.treasury-account-grant-journal.v1',
                'domain' => 'treasury_account_grant',
                'provider_call' => false,
            ],
        ));
    }
}
