<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Treasury;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use LBHurtado\XChange\Models\TreasuryInstitutionFundClassification;
use LBHurtado\XJournal\Data\ExecutionActorData;
use LBHurtado\XJournal\Data\ExecutionJournalEntryData;
use LBHurtado\XJournal\Data\ExecutionReferenceData;
use LBHurtado\XJournal\Data\ExecutionSubjectData;
use LBHurtado\XJournal\Services\ExecutionJournalRecorder;

final readonly class TreasuryInstitutionFundClassificationJournal
{
    public function __construct(private ExecutionJournalRecorder $recorder) {}

    public function record(
        TreasuryInstitutionFundClassification $classification,
        string $eventType,
        Model|string $actor,
    ): void {
        $this->recorder->record(new ExecutionJournalEntryData(
            eventType: $eventType,
            occurredAt: CarbonImmutable::parse($classification->updated_at),
            actor: $actor instanceof Model
                ? new ExecutionActorData((string) $actor->getKey(), $actor->getMorphClass())
                : new ExecutionActorData($actor, 'system_principal'),
            subject: new ExecutionSubjectData(
                id: $classification->reference,
                type: 'treasury_institution_fund_classification',
                display: 'Institution-Owned Funds Classification',
            ),
            references: new ExecutionReferenceData(
                correlationId: 'treasury-institution-funds:'.$classification->reference,
                causationId: $eventType,
                executionId: (string) $classification->getKey(),
                externalReference: $classification->operation_reference
                    ?? $classification->evidence_operation_reference,
                metadata: ['request_hash' => $classification->request_hash],
            ),
            idempotencyKey: 'x-change:treasury-institution-funds:'.$eventType.':'.$classification->getKey(),
            payload: [
                'status' => $classification->status->value,
                'amount_minor' => $classification->amount_minor,
                'currency' => $classification->currency,
                'connection_reference' => $classification->connection_reference,
                'evidence_operation_reference' => $classification->evidence_operation_reference,
                'source_position_reference' => $classification->source_position_reference,
                'destination_position_reference' => $classification->destination_position_reference,
                'operation_reference' => $classification->operation_reference,
            ],
            metadata: [
                'schema' => 'x-change.treasury-institution-funds-journal.v1',
                'domain' => 'treasury_institution_funds',
                'provider_call' => false,
            ],
        ));
    }
}
