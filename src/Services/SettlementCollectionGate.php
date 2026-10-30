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

        if (SettlementRepaymentPolicy::forVoucher($voucher)) {
            if (! SettlementRepaymentPolicy::disbursed($voucher)) {
                throw new \RuntimeException('Repayments become available after the initial disbursement succeeds.');
            }

            return;
        }

        if (! $capabilities->requires_envelope) {
            return;
        }

        $resolvedContext = [
            ...$this->contextFromVoucher($voucher),
            ...$context,
            'requires_envelope' => true,
        ];

        if (data_get($voucher->metadata, 'instructions.execution.schema') === 'voucher.execution.v2'
            && data_get($voucher->metadata, 'instructions.execution.settlement.driver') === 'philhealth-bst') {
            $this->assertVerifiedPhilhealthReview($voucher);
        }

        $this->settlementGate->ensureReady(
            voucher: $voucher,
            gate: (string) $resolvedContext['gate'],
            context: $resolvedContext,
        );
    }

    private function assertVerifiedPhilhealthReview(Voucher $voucher): void
    {
        $metadata = (array) $voucher->metadata;
        $review = data_get($metadata, 'settlement_envelope.amount_review');
        $claimId = data_get($metadata, 'settlement_envelope.attestation.claim_id');
        $manifest = data_get($metadata, 'settlement_envelope.attestation.evidence_manifest_sha256');
        $claim = $voucher->claims()->whereKey($claimId)->first();
        if (! is_array($review) || $claim === null || ! is_string($manifest)
            || ! hash_equals($manifest, (string) data_get($claim->meta, 'evidence.manifest_sha256'))
            || ! hash_equals($manifest, (string) ($review['evidence_manifest_sha256'] ?? ''))
            || ($review['amount_minor'] ?? null) !== (int) round((float) $voucher->target_amount * 100)
            || empty($review['reference']) || empty($review['reviewer_type']) || empty($review['reviewer_id'])) {
            throw new VoucherRequiresSettlementEnvelope(
                voucher: $voucher,
                capabilities: $this->flowResolver->resolve($voucher),
                message: 'PhilHealth settlement requires an authorized amount review tied to attestation evidence.',
            );
        }
    }

    public function assertSettlementDoesNotDisburse(Voucher $voucher): void
    {
        $capabilities = $this->flowResolver->resolve($voucher);

        if (! $capabilities->type->isSettlement()) {
            return;
        }

        if (SettlementRepaymentPolicy::forVoucher($voucher)) {
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

        $envelope = (array) data_get($metadata, 'settlement_envelope', []);
        $isV2 = data_get($metadata, 'instructions.execution.schema') === 'voucher.execution.v2';
        $v2Settlement = $isV2 ? data_get($metadata, 'instructions.execution.settlement') : null;
        if ($isV2 && (! is_array($v2Settlement) || ! is_string($v2Settlement['driver'] ?? null) || trim($v2Settlement['driver']) === '')) {
            throw new \InvalidArgumentException('V2 settlement requires an explicit driver.');
        }
        if ($isV2 && isset($envelope['driver']) && $envelope['driver'] !== $v2Settlement['driver']) {
            throw new \InvalidArgumentException('Settlement envelope driver does not match the v2 instruction.');
        }

        return [
            'requires_envelope' => true,
            'driver' => ($isV2 ? $v2Settlement['driver'] : null) ?? data_get($envelope, 'driver')
                ?? $metadata['settlement_driver']
                    ?? $metadata['envelope_driver']
                    ?? data_get($metadata, 'instructions.metadata.settlement_driver')
                    ?? data_get($metadata, 'instructions.metadata.envelope_driver')
                    ?? data_get($metadata, 'instructions.metadata.custom.settlement.driver')
                    ?? (data_get($metadata, 'instructions.claim.default_outcome') === 'lead_intake'
                        ? 'claim-intake'
                        : null)
                    ?? config('x-change.settlement.default_driver', 'philhealth-bst'),
            'gate' => $isV2 ? ($v2Settlement['readiness_gate'] ?? 'settleable') : config('x-change.settlement.default_gate', 'settleable'),
            'payload' => (array) (
                data_get($envelope, 'payload')
                ?? $metadata['settlement_payload']
                ?? []
            ),
            'documents' => (array) (
                data_get($envelope, 'documents')
                ?? $metadata['settlement_documents']
                ?? []
            ),
            'checklist' => (array) (
                data_get($envelope, 'checklist')
                ?? $metadata['settlement_checklist']
                ?? []
            ),
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
