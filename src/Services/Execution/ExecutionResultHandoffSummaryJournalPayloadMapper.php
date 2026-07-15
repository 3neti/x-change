<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Execution;

use Illuminate\Support\Arr;
use LBHurtado\XChange\Data\Execution\ExecutionResultHandoffResultData;
use LBHurtado\XChange\Data\Execution\ExecutionResultHandoffSummaryData;
use LBHurtado\XChange\Data\Execution\ExecutionResultHandoffSummaryJournalPayloadData;

class ExecutionResultHandoffSummaryJournalPayloadMapper
{
    private const IDEMPOTENCY_NAMESPACE = 'execution.handoff.summary';

    public function map(ExecutionResultHandoffSummaryData $summary): ExecutionResultHandoffSummaryJournalPayloadData
    {
        return new ExecutionResultHandoffSummaryJournalPayloadData(
            event_name: $this->eventType(),
            idempotency_key: $this->idempotencyKey($summary),
            subject: [
                'type' => 'pay_code',
                'reference' => (string) $summary->voucher_code,
            ],
            references: [
                'execution_id' => $summary->execution_id,
                'voucher_code' => (string) $summary->voucher_code,
                'correlation_id' => $summary->correlation_id,
                'causation_id' => $summary->execution_id,
            ],
            payload: [
                'execution_id' => $summary->execution_id,
                'voucher_code' => $summary->voucher_code,
                'correlation_id' => $summary->correlation_id,
                'status' => $summary->status,
                'blocks_execution' => $summary->blocks_execution,
                'profile' => $summary->toReportArray()['profile'],
                'journal' => $this->safeResult($summary->results['journal'] ?? null),
                'action' => $this->safeResult($summary->results['action'] ?? null),
                'feedback' => $this->safeResult($summary->results['feedback'] ?? null),
                'cockpit_activity' => $this->safeResult($summary->results['cockpit_activity'] ?? null),
            ],
            metadata: [
                'source' => 'x-change.execution',
                'summary_event_source' => config(
                    'x-change.execution_result_handoffs.durable_evidence_source',
                    'post_pipeline_summary_journal_event',
                ),
                'redactions' => [
                    'raw_handoff_payloads_exposed' => false,
                    'transport_secrets_exposed' => false,
                    'provider_payloads_exposed' => false,
                    'wallet_payloads_exposed' => false,
                ],
            ],
        );
    }

    private function eventType(): string
    {
        $configured = config('x-change.execution_result_handoffs.durable_evidence_event_type');

        return is_string($configured) && trim($configured) !== ''
            ? $configured
            : 'execution.handoff.summary.recorded';
    }

    private function idempotencyKey(ExecutionResultHandoffSummaryData $summary): string
    {
        return hash('sha256', implode('|', [
            self::IDEMPOTENCY_NAMESPACE,
            $summary->execution_id ?? '',
            $summary->voucher_code ?? '',
            $summary->correlation_id ?? '',
            hash('sha256', json_encode($summary->toReportArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
        ]));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function safeResult(mixed $result): ?array
    {
        if (! $result instanceof ExecutionResultHandoffResultData) {
            return null;
        }

        return [
            'target' => $result->target,
            'status' => $result->status,
            'execution_id' => $result->execution_id,
            'voucher_code' => $result->voucher_code,
            'correlation_id' => $result->correlation_id,
            'blocking' => $result->blocking,
            'performed_side_effect' => $result->performed_side_effect,
            'source' => $result->source,
            'reason' => $result->reason,
            'metadata' => $this->safeMetadata($result->metadata),
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function safeMetadata(array $metadata): array
    {
        Arr::forget($metadata, [
            'raw',
            'raw_payload',
            'raw_provider_payload',
            'provider_payload',
            'wallet',
            'funding_source',
            'recipient_secret',
            'otp',
            'transport_secret',
        ]);

        if (isset($metadata['plan_items']) && is_array($metadata['plan_items'])) {
            $metadata['plan_items'] = array_map(
                fn (mixed $item): mixed => is_array($item)
                    ? Arr::except($item, ['transport_secret', 'secret', 'token', 'authorization', 'headers'])
                    : $item,
                $metadata['plan_items'],
            );
        }

        return $metadata;
    }
}
