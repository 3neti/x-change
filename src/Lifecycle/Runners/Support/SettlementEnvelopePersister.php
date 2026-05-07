<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Runners\Support;

final class SettlementEnvelopePersister
{
    public function completeEnvelope(
        mixed $voucher,
        array $scenario,
        array $readyContext,
    ): mixed {
        $metadata = is_array($voucher->metadata ?? null)
            ? $voucher->metadata
            : [];

        $existingEnvelope = (array) data_get($metadata, 'settlement_envelope', []);

        $settlementPayload = [
            ...(array) data_get($existingEnvelope, 'payload', []),
            ...(array) data_get($readyContext, 'payload', []),
        ];

        $settlementDocuments = [
            ...(array) data_get($existingEnvelope, 'documents', []),
            ...(array) data_get($readyContext, 'documents', []),
        ];

        $settlementChecklist = [
            ...(array) data_get($existingEnvelope, 'checklist', []),
            ...(array) data_get($readyContext, 'checklist', []),
        ];

        $driver = (string) (
            data_get($scenario, 'metadata.settlement_driver')
                ?: config('x-change.settlement.default_driver', 'philhealth-bst')
        );

        $settlementEnvelope = [
            ...$existingEnvelope,
            'driver' => $driver,
            'payload' => $settlementPayload,
            'documents' => $settlementDocuments,
            'checklist' => $settlementChecklist,
            'updated_at' => now()->toISOString(),
        ];

        $voucher->forceFill([
            'metadata' => [
                ...$metadata,
                'flow_type' => 'settlement',
                'settlement_driver' => $driver,
                'settlement_envelope' => $settlementEnvelope,
                'settlement_payload' => $settlementPayload,
                'settlement_documents' => $settlementDocuments,
                'settlement_checklist' => $settlementChecklist,
            ],
        ])->save();

        return $voucher->refresh();
    }
}
