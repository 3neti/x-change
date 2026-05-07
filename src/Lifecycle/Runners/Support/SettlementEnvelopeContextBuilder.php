<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Runners\Support;

final class SettlementEnvelopeContextBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function fromScenarioAttempt(array $scenario, array $attempt): array
    {
        return [
            'requires_envelope' => true,
            'driver' => $this->resolveDriver($scenario, $attempt),
            'payload' => (array) data_get($attempt, 'settlement.payload', []),
            'documents' => (array) data_get($attempt, 'settlement.documents', []),
            'checklist' => (array) data_get($attempt, 'settlement.checklist', []),
            'wallet_info' => (array) data_get($attempt, 'settlement.wallet_info', []),
            'bio_fields' => (array) data_get($attempt, 'settlement.bio_fields', []),
            'claims' => (array) data_get($attempt, 'settlement.claims', []),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function fromPersistedEnvelopeAndScenarioPhase(
        mixed $voucher,
        array $scenario,
        string $phaseKey,
    ): array {
        $metadata = is_array($voucher->metadata ?? null) ? $voucher->metadata : [];

        $persistedEnvelope = (array) data_get($metadata, 'settlement_envelope', []);
        $phaseSettlement = (array) data_get($scenario, "phases.{$phaseKey}.settlement", []);

        return [
            'requires_envelope' => true,
            'driver' => $this->resolveDriver($scenario),
            'payload' => [
                ...(array) data_get($persistedEnvelope, 'payload', []),
                ...(array) data_get($phaseSettlement, 'payload', []),
            ],
            'documents' => [
                ...(array) data_get($persistedEnvelope, 'documents', []),
                ...(array) data_get($phaseSettlement, 'documents', []),
            ],
            'checklist' => [
                ...(array) data_get($persistedEnvelope, 'checklist', []),
                ...(array) data_get($phaseSettlement, 'checklist', []),
            ],
        ];
    }

    public function resolveGate(array $scenario, array $attempt = []): string
    {
        return (string) (
            data_get($attempt, 'settlement.gate')
                ?: data_get($scenario, 'settlement.gate')
                ?: config('x-change.settlement.default_gate', 'settleable')
        );
    }

    public function resolveDriver(array $scenario, array $attempt = []): string
    {
        return (string) (
            data_get($attempt, 'settlement.driver')
                ?: data_get($scenario, 'settlement.driver')
                ?: data_get($scenario, 'metadata.settlement_driver')
                    ?: config('x-change.settlement.default_driver', 'philhealth-bst')
        );
    }
}
