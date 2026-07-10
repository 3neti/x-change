<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use LBHurtado\XChange\Contracts\CockpitOperatorIssuanceActivityJournalHandoffContract;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityItemData;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityJournalHandoffResultData;
use LBHurtado\XChange\Data\Cockpit\CockpitOperatorIssuanceActivityJournalPayloadData;
use LBHurtado\XJournal\Data\ExecutionActorData;
use LBHurtado\XJournal\Data\ExecutionJournalEntryData;
use LBHurtado\XJournal\Data\ExecutionMoneyData;
use LBHurtado\XJournal\Data\ExecutionReferenceData;
use LBHurtado\XJournal\Data\ExecutionSubjectData;
use LBHurtado\XJournal\Services\ExecutionJournalRecorder;
use Throwable;

class XJournalCockpitOperatorIssuanceActivityJournalHandoff implements CockpitOperatorIssuanceActivityJournalHandoffContract
{
    public function __construct(
        private readonly CockpitOperatorIssuanceActivityJournalPayloadMapper $mapper,
        private readonly ExecutionJournalRecorder $recorder,
    ) {}

    public function handoff(CockpitOperatorIssuanceActivityItemData $activity): CockpitOperatorIssuanceActivityJournalHandoffResultData
    {
        $payload = $this->mapper->map($activity);

        try {
            $entry = $this->recorder->record($this->entryData($payload));

            return new CockpitOperatorIssuanceActivityJournalHandoffResultData(
                status: 'recorded',
                activity_id: $activity->id,
                correlation_id: $activity->correlation_id,
                journal_entry_id: (string) $entry->getKey(),
                writes_journal: true,
                source: 'x-journal-execution-journal-recorder',
                reason: 'Cockpit durable activity was handed off to x-journal.',
                metadata: [
                    'reference_number' => $entry->reference_number,
                    'idempotency_key' => $payload->idempotency_key,
                    'event_type' => $payload->event_name,
                ],
            );
        } catch (Throwable $exception) {
            return new CockpitOperatorIssuanceActivityJournalHandoffResultData(
                status: 'failed_non_blocking',
                activity_id: $activity->id,
                correlation_id: $activity->correlation_id,
                writes_journal: false,
                source: 'x-journal-execution-journal-recorder',
                reason: 'x-journal handoff failed without blocking the Cockpit activity flow.',
                metadata: [
                    'idempotency_key' => $payload->idempotency_key,
                    'event_type' => $payload->event_name,
                    'exception' => $exception::class,
                ],
            );
        }
    }

    private function entryData(CockpitOperatorIssuanceActivityJournalPayloadData $payload): ExecutionJournalEntryData
    {
        return new ExecutionJournalEntryData(
            eventType: $payload->event_name,
            occurredAt: $this->occurredAt($payload),
            actor: new ExecutionActorData(
                id: $this->nullableString($payload->actor['id'] ?? null),
                type: $this->nullableString($payload->actor['type'] ?? null),
            ),
            subject: new ExecutionSubjectData(
                id: $this->nullableString($payload->subject['reference'] ?? null),
                type: $this->nullableString($payload->subject['type'] ?? null),
                display: $this->nullableString($payload->subject['reference'] ?? null),
            ),
            references: new ExecutionReferenceData(
                correlationId: $this->nullableString($payload->references['correlation_id'] ?? null),
                causationId: $this->nullableString($payload->references['causation_id'] ?? null),
                externalReference: $this->nullableString($payload->references['activity_id'] ?? null),
                metadata: [
                    'activity_id' => $payload->references['activity_id'] ?? null,
                ],
            ),
            idempotencyKey: $payload->idempotency_key,
            payload: $payload->payload,
            money: new ExecutionMoneyData(
                amount: $this->nullableString($payload->payload['amount'] ?? null),
                currency: $this->nullableString($payload->payload['currency'] ?? null),
            ),
            metadata: [
                ...$payload->metadata,
                'schema' => $payload->schema,
                'domain' => $payload->domain,
                'source' => $payload->metadata['source'] ?? 'x-change.cockpit',
            ],
        );
    }

    private function occurredAt(CockpitOperatorIssuanceActivityJournalPayloadData $payload): CarbonInterface
    {
        $issuedAt = $payload->payload['issued_at'] ?? null;

        if (is_string($issuedAt) && trim($issuedAt) !== '') {
            return CarbonImmutable::parse($issuedAt);
        }

        return CarbonImmutable::now();
    }

    private function nullableString(mixed $value): ?string
    {
        if (is_scalar($value) && trim((string) $value) !== '') {
            return (string) $value;
        }

        return null;
    }
}
