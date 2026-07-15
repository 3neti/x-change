<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Execution;

use Carbon\CarbonImmutable;
use LBHurtado\Voucher\Data\ExecutionContextData;
use LBHurtado\Voucher\Data\ExecutionResultData;
use LBHurtado\XChange\Contracts\ExecutionResultJournalHandoffContract;
use LBHurtado\XChange\Data\Execution\ExecutionResultHandoffResultData;
use LBHurtado\XChange\Data\Execution\ExecutionResultJournalPayloadData;
use LBHurtado\XJournal\Data\ExecutionActorData;
use LBHurtado\XJournal\Data\ExecutionJournalEntryData;
use LBHurtado\XJournal\Data\ExecutionMoneyData;
use LBHurtado\XJournal\Data\ExecutionReferenceData;
use LBHurtado\XJournal\Data\ExecutionSubjectData;
use LBHurtado\XJournal\Services\ExecutionJournalRecorder;
use Throwable;

class XJournalExecutionResultJournalHandoff implements ExecutionResultJournalHandoffContract
{
    public function __construct(
        private readonly ExecutionResultJournalPayloadMapper $mapper,
        private readonly ExecutionJournalRecorder $recorder,
    ) {}

    public function handoff(ExecutionResultData $result, ExecutionContextData $context): ExecutionResultHandoffResultData
    {
        $payload = $this->mapper->map($result, $context);

        try {
            $entry = $this->recorder->record($this->entryData($payload));

            return new ExecutionResultHandoffResultData(
                target: 'journal',
                status: 'recorded',
                execution_id: $result->execution_id,
                voucher_code: $context->voucherCode,
                correlation_id: $this->nullableString($payload->references['correlation_id'] ?? null),
                blocking: false,
                performed_side_effect: true,
                source: 'x-journal-execution-journal-recorder',
                reason: 'Execution result was handed off to x-journal.',
                metadata: [
                    'journal_entry_id' => (string) $entry->getKey(),
                    'reference_number' => $entry->reference_number,
                    'idempotency_key' => $payload->idempotency_key,
                    'event_type' => $payload->event_name,
                ],
            );
        } catch (Throwable $exception) {
            return new ExecutionResultHandoffResultData(
                target: 'journal',
                status: 'failed_non_blocking',
                execution_id: $result->execution_id,
                voucher_code: $context->voucherCode,
                correlation_id: $this->nullableString($payload->references['correlation_id'] ?? null),
                blocking: false,
                performed_side_effect: false,
                source: 'x-journal-execution-journal-recorder',
                reason: 'x-journal execution-result handoff failed without blocking the execution result.',
                metadata: [
                    'idempotency_key' => $payload->idempotency_key,
                    'event_type' => $payload->event_name,
                    'exception' => $exception::class,
                ],
            );
        }
    }

    private function entryData(ExecutionResultJournalPayloadData $payload): ExecutionJournalEntryData
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
                externalReference: $this->nullableString($payload->references['execution_id'] ?? null),
                metadata: [
                    'execution_id' => $payload->references['execution_id'] ?? null,
                    'voucher_code' => $payload->references['voucher_code'] ?? null,
                ],
            ),
            idempotencyKey: $payload->idempotency_key,
            payload: $payload->payload,
            money: new ExecutionMoneyData(
                amount: $this->nullableString(data_get($payload->payload, 'reconciliation.amount.value')),
                currency: $this->nullableString(data_get($payload->payload, 'reconciliation.amount.currency')),
            ),
            metadata: [
                ...$payload->metadata,
                'payload_schema' => $payload->schema,
                'domain' => $payload->domain,
            ],
        );
    }

    private function nullableString(mixed $value): ?string
    {
        if (is_scalar($value) && trim((string) $value) !== '') {
            return (string) $value;
        }

        return null;
    }
}
