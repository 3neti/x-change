<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Execution;

use LBHurtado\Voucher\Contracts\SettlementEnvelopeExecutionGateway;
use LBHurtado\Voucher\Data\ExecutionContextData;
use LBHurtado\Voucher\Exceptions\SettlementEnvelopeNotReadyException;
use LBHurtado\XChange\Contracts\SettlementEnvelopeReadinessContract;

final class XChangeSettlementEnvelopeExecutionGateway implements SettlementEnvelopeExecutionGateway
{
    public function __construct(
        private readonly SettlementEnvelopeReadinessContract $readiness,
    ) {}

    public function load(ExecutionContextData $context): array
    {
        $metadata = $this->settlementEnvelopeMetadata($context);

        return [
            'reference' => $metadata['reference'] ?? $context->voucherCode,
            'readiness_gate' => $metadata['readiness_gate'] ?? 'settleable',
            'child_generation' => $metadata['child_generation'] ?? 'none',
            'metadata' => $metadata,
            'voucher_code' => $context->voucherCode,
        ];
    }

    public function assertReady(mixed $envelope, ExecutionContextData $context): void
    {
        $metadata = $this->settlementEnvelopeMetadata($context);

        if (array_key_exists('ready', $metadata) && ! (bool) $metadata['ready']) {
            throw new SettlementEnvelopeNotReadyException('Settlement envelope metadata is not ready.');
        }

        if ($context->voucher === null) {
            return;
        }

        $readiness = $this->readiness->evaluate(
            voucher: $context->voucher,
            gate: (string) data_get($envelope, 'readiness_gate', 'settleable'),
            context: [
                'requires_envelope' => true,
                'payload' => data_get($metadata, 'payload', []),
                'documents' => data_get($metadata, 'documents', []),
                'checklist' => data_get($metadata, 'checklist', []),
                'driver' => data_get($metadata, 'driver', config('x-change.settlement.default_driver', 'philhealth-bst')),
            ],
        );

        if (! $readiness->ready) {
            throw new SettlementEnvelopeNotReadyException('Settlement envelope is not ready for execution.');
        }
    }

    public function lock(mixed $envelope, ExecutionContextData $context): array
    {
        return [
            ...$this->normalizeEnvelope($envelope),
            'locked' => true,
            'locked_at' => now()->toISOString(),
            'locked_by' => 'x-change.execution-gateway',
        ];
    }

    public function childVoucherInstructions(mixed $envelope, ExecutionContextData $context): array
    {
        $children = data_get($this->settlementEnvelopeMetadata($context), 'children', []);

        return is_array($children) ? array_values($children) : [];
    }

    public function claimFallbackInstructions(mixed $envelope, array $childInstruction, ExecutionContextData $context): ?array
    {
        $fallback = data_get($this->settlementEnvelopeMetadata($context), 'fallback_instruction');

        return is_array($fallback) ? $fallback : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function settlementEnvelopeMetadata(ExecutionContextData $context): array
    {
        $metadata = data_get($context->instruction?->metadata, 'settlement_envelope', []);

        if (! is_array($metadata)) {
            $metadata = [];
        }

        return [
            ...$metadata,
            'reference' => $metadata['reference']
                ?? $context->instruction?->metadata['envelope_reference']
                ?? $context->voucherCode,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeEnvelope(mixed $envelope): array
    {
        return is_array($envelope) ? $envelope : [
            'reference' => data_get($envelope, 'reference'),
        ];
    }
}
