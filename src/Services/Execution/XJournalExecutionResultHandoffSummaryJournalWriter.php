<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Execution;

use Carbon\CarbonImmutable;
use LBHurtado\XChange\Contracts\ExecutionResultHandoffSummaryJournalWriterContract;
use LBHurtado\XChange\Data\Execution\ExecutionResultHandoffResultData;
use LBHurtado\XChange\Data\Execution\ExecutionResultHandoffSummaryData;
use LBHurtado\XChange\Data\Execution\ExecutionResultHandoffSummaryJournalPayloadData;
use LBHurtado\XJournal\Data\ExecutionActorData;
use LBHurtado\XJournal\Data\ExecutionJournalEntryData;
use LBHurtado\XJournal\Data\ExecutionReferenceData;
use LBHurtado\XJournal\Data\ExecutionSubjectData;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;
use LBHurtado\XJournal\Services\ExecutionJournalRecorder;
use Throwable;

class XJournalExecutionResultHandoffSummaryJournalWriter implements ExecutionResultHandoffSummaryJournalWriterContract
{
    public function __construct(
        private readonly ExecutionResultHandoffSummaryJournalPayloadMapper $mapper,
        private readonly ExecutionJournalRecorder $recorder,
    ) {}

    public function write(ExecutionResultHandoffSummaryData $summary): ExecutionResultHandoffResultData
    {
        $payload = $this->mapper->map($summary);

        try {
            $existing = $this->existingEntry($payload);

            if ($existing !== null) {
                return $this->recordedResult($summary, $payload, $existing);
            }

            $entry = $this->recorder->record($this->entryData($payload));

            return $this->recordedResult($summary, $payload, $entry);
        } catch (Throwable $exception) {
            return new ExecutionResultHandoffResultData(
                target: 'handoff_summary_journal',
                status: 'failed_non_blocking',
                execution_id: $summary->execution_id,
                voucher_code: $summary->voucher_code,
                correlation_id: $this->nullableString($payload->references['correlation_id'] ?? null),
                blocking: false,
                performed_side_effect: false,
                source: 'x-journal-execution-handoff-summary-writer',
                reason: 'x-journal post-pipeline execution handoff summary write failed without blocking the execution result.',
                metadata: [
                    'idempotency_key' => $payload->idempotency_key,
                    'event_type' => $payload->event_name,
                    'exception' => $exception::class,
                ],
            );
        }
    }

    private function entryData(ExecutionResultHandoffSummaryJournalPayloadData $payload): ExecutionJournalEntryData
    {
        return new ExecutionJournalEntryData(
            eventType: $payload->event_name,
            occurredAt: CarbonImmutable::now(),
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
                executionId: $this->nullableString($payload->references['execution_id'] ?? null),
                externalReference: $this->nullableString($payload->references['execution_id'] ?? null),
                metadata: [
                    'execution_id' => $payload->references['execution_id'] ?? null,
                    'voucher_code' => $payload->references['voucher_code'] ?? null,
                    'summary_event_source' => $payload->metadata['summary_event_source'] ?? null,
                ],
            ),
            idempotencyKey: $payload->idempotency_key,
            payload: $payload->payload,
            metadata: [
                ...$payload->metadata,
                'payload_schema' => $payload->schema,
                'domain' => $payload->domain,
            ],
        );
    }

    private function recordedResult(
        ExecutionResultHandoffSummaryData $summary,
        ExecutionResultHandoffSummaryJournalPayloadData $payload,
        ExecutionJournalEntry $entry,
    ): ExecutionResultHandoffResultData {
        return new ExecutionResultHandoffResultData(
            target: 'handoff_summary_journal',
            status: 'recorded',
            execution_id: $summary->execution_id,
            voucher_code: $summary->voucher_code,
            correlation_id: $this->nullableString($payload->references['correlation_id'] ?? null),
            blocking: false,
            performed_side_effect: true,
            source: 'x-journal-execution-handoff-summary-writer',
            reason: 'Post-pipeline execution handoff summary was handed off to x-journal.',
            metadata: [
                'journal_entry_id' => (string) $entry->getKey(),
                'reference_number' => $entry->reference_number,
                'idempotency_key' => $payload->idempotency_key,
                'event_type' => $payload->event_name,
            ],
        );
    }

    private function existingEntry(ExecutionResultHandoffSummaryJournalPayloadData $payload): ?ExecutionJournalEntry
    {
        if (! config('x-journal.idempotency.enabled', true) || $payload->idempotency_key === '') {
            return null;
        }

        return ExecutionJournalEntry::query()
            ->where('idempotency_key', $payload->idempotency_key)
            ->first();
    }

    private function nullableString(mixed $value): ?string
    {
        if (is_scalar($value) && trim((string) $value) !== '') {
            return (string) $value;
        }

        return null;
    }
}
