<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Execution;

use LBHurtado\Voucher\Data\ExecutionContextData;
use LBHurtado\Voucher\Data\ExecutionResultData;
use LBHurtado\XChange\Data\Execution\ExecutionResultJournalPayloadData;

class ExecutionResultJournalPayloadMapper
{
    private const IDEMPOTENCY_NAMESPACE = 'execution.result';

    public function map(ExecutionResultData $result, ExecutionContextData $context): ExecutionResultJournalPayloadData
    {
        $correlationId = $this->correlationId($context);

        return new ExecutionResultJournalPayloadData(
            idempotency_key: $this->idempotencyKey($result, $context),
            subject: [
                'type' => 'pay_code',
                'reference' => $context->voucherCode,
            ],
            references: [
                'execution_id' => $result->execution_id,
                'voucher_code' => $context->voucherCode,
                'correlation_id' => $correlationId,
                'causation_id' => $result->execution_id,
            ],
            payload: [
                'execution_id' => $result->execution_id,
                'voucher_code' => $context->voucherCode,
                'driver' => $result->driver,
                'status' => $result->status,
                'successful' => $result->successful,
                'events' => $result->events,
                'failure' => $result->failure,
                'provider_references' => $result->providerReferences,
                'reconciliation' => $this->safeReconciliation($result->reconciliation),
                'children' => $result->children,
                'metadata' => $this->safeMetadata($result->metadata),
            ],
            metadata: [
                'source' => 'x-change.execution',
                'driver' => $result->driver,
                'status' => $result->status,
                'successful' => $result->successful,
                'schema' => 'voucher.execution.v1',
                'redactions' => [
                    'raw_provider_payloads_exposed' => false,
                    'raw_reconciliation_payloads_exposed' => false,
                    'unmasked_account_numbers_exposed' => false,
                ],
            ],
        );
    }

    private function idempotencyKey(ExecutionResultData $result, ExecutionContextData $context): string
    {
        return hash('sha256', implode('|', [
            self::IDEMPOTENCY_NAMESPACE,
            $result->execution_id ?? '',
            $context->voucherCode,
            $this->correlationId($context) ?? '',
        ]));
    }

    private function correlationId(ExecutionContextData $context): ?string
    {
        $correlation = $context->correlation['correlation_id']
            ?? $context->correlation['idempotency_key']
            ?? $context->voucherCode;

        return is_scalar($correlation) ? (string) $correlation : null;
    }

    /**
     * @param  array<string, mixed>  $reconciliation
     * @return array<string, mixed>
     */
    private function safeReconciliation(array $reconciliation): array
    {
        unset($reconciliation['raw']);

        if (isset($reconciliation['destination_account']) && is_array($reconciliation['destination_account'])) {
            unset($reconciliation['destination_account']['account_number']);
        }

        return $reconciliation;
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function safeMetadata(array $metadata): array
    {
        unset(
            $metadata['raw'],
            $metadata['raw_payload'],
            $metadata['provider_payload'],
            $metadata['wallet'],
            $metadata['funding_source'],
            $metadata['recipient_secret'],
            $metadata['otp'],
        );

        if (isset($metadata['destination_account']) && is_array($metadata['destination_account'])) {
            unset($metadata['destination_account']['account_number']);
        }

        return $metadata;
    }
}
