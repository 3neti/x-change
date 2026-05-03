<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use Illuminate\Support\Arr;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\SettlementReadinessGateContract;
use LBHurtado\XChange\Contracts\VoucherFlowCapabilityResolverContract;
use LBHurtado\XChange\Exceptions\VoucherRequiresSettlementEnvelope;

class SettlementCollectionGate
{
    public function __construct(
        protected VoucherFlowCapabilityResolverContract $flowResolver,
        protected SettlementReadinessGateContract $settlementGate,
    ) {}

    public function ensureCollectibleSettlementIsReady(
        Voucher $voucher,
        array $context = [],
    ): void {
        $capabilities = $this->flowResolver->resolve($voucher);

        if (! $capabilities->type->isSettlement()) {
            return;
        }

        if (! $capabilities->requires_envelope) {
            return;
        }

        $this->settlementGate->ensureReady(
            voucher: $voucher,
            gate: (string) ($context['gate'] ?? config('x-change.settlement.default_gate', 'settleable')),
            context: [
                ...$this->contextFromVoucher($voucher),
                ...$context,
                'requires_envelope' => true,
            ],
        );
    }

    public function assertSettlementDoesNotDisburse(Voucher $voucher): void
    {
        $capabilities = $this->flowResolver->resolve($voucher);

        if (! $capabilities->type->isSettlement()) {
            return;
        }

        throw new VoucherRequiresSettlementEnvelope(
            voucher: $voucher,
            capabilities: $capabilities,
            message: 'Settlement vouchers cannot be disbursed to the claimant. They must be collected through the settlement payment flow.',
        );
    }

    public function contextFromVoucher(Voucher $voucher): array
    {
        /**
         * Important:
         *
         * Settlement evidence may live in either:
         *
         * 1. voucher.metadata
         * 2. voucher.instructions.metadata
         *
         * issueVoucher(validVoucherInstructions(overrides: ['metadata' => ...]))
         * commonly stores metadata inside the voucher instructions payload.
         */
        $metadata = [
            ...$this->rawInstructionsMetadata($voucher),
            ...$this->rawMetadata($voucher),
        ];

        return [
            'requires_envelope' => true,
            'driver' => $metadata['settlement_driver']
                ?? $metadata['envelope_driver']
                    ?? config('x-change.settlement.default_driver', 'philhealth-bst'),
            'gate' => config('x-change.settlement.default_gate', 'settleable'),
            'payload' => $metadata['settlement_payload'] ?? [],
            'documents' => $metadata['settlement_documents'] ?? [],
            'checklist' => $metadata['settlement_checklist'] ?? [],
            'wallet_info' => $metadata['wallet_info'] ?? [],
            'bio_fields' => $metadata['bio_fields'] ?? [],
            'claims' => $metadata['settlement_claims'] ?? [],
        ];
    }

    protected function rawMetadata(Voucher $voucher): array
    {
        $raw = $voucher->getAttributes()['metadata'] ?? null;

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        if (is_array($raw)) {
            return $raw;
        }

        $metadata = $voucher->metadata ?? null;

        return is_array($metadata) ? $metadata : [];
    }

    protected function rawInstructionsMetadata(Voucher $voucher): array
    {
        $raw = $voucher->getAttributes()['instructions'] ?? null;

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);

            return is_array($decoded)
                ? (array) Arr::get($decoded, 'metadata', [])
                : [];
        }

        if (is_array($raw)) {
            return (array) Arr::get($raw, 'metadata', []);
        }

        /**
         * Last-resort fallback only.
         *
         * Some voucher package casts may expose instructions as an object.
         * Avoid depending on this first because it can trigger validation in
         * some test contexts.
         */
        $instructions = $voucher->instructions ?? null;

        if (is_object($instructions) && isset($instructions->metadata)) {
            return is_array($instructions->metadata)
                ? $instructions->metadata
                : [];
        }

        return [];
    }
}
