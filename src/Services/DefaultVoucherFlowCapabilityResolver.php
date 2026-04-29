<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use BackedEnum;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\VoucherFlowCapabilityResolverContract;
use LBHurtado\XChange\Data\VoucherFlow\VoucherFlowCapabilitiesData;
use LBHurtado\XChange\Enums\VoucherFlowType;

/**
 * Resolves the canonical flow capabilities of a voucher.
 *
 * The resolver treats an explicit `flow_type` as authoritative. This is
 * important for lifecycle scenarios such as collectible Pay Codes, where the
 * voucher may still look "disbursable" from legacy/default fields but the
 * instruction metadata intentionally declares a different behavior.
 *
 * Resolution priority:
 *
 * 1. Explicit instruction metadata:
 *      - instructions.metadata.flow_type
 *      - metadata.instructions.metadata.flow_type
 *      - metadata.instructions.rules.metadata.flow_type
 *
 * 2. Direct voucher/model metadata:
 *      - flow_type
 *      - voucher_flow_type
 *      - metadata.flow_type
 *      - meta.flow_type
 *
 * 3. Legacy voucher type fallback:
 *      - voucher_type
 *      - instructions.voucher_type
 *      - metadata.instructions.voucher_type
 *
 * 4. Config default:
 *      - config('x-change.voucher_flow_types.default', 'disbursable')
 *
 * This preserves backward compatibility while allowing newer instruction
 * contracts to override inferred behavior explicitly.
 */
class DefaultVoucherFlowCapabilityResolver implements VoucherFlowCapabilityResolverContract
{
    public function resolve(Voucher $voucher): VoucherFlowCapabilitiesData
    {
        $type = $this->typeOf($voucher);

        $config = (array) config("x-change.voucher_flow_types.canonical.{$type->value}", []);

        return new VoucherFlowCapabilitiesData(
            type: $type,
            label: (string) ($config['label'] ?? $type->label()),
            direction: (string) ($config['direction'] ?? $type->direction()),
            can_disburse: (bool) ($config['can_disburse'] ?? false),
            can_collect: (bool) ($config['can_collect'] ?? false),
            can_settle: (bool) ($config['can_settle'] ?? false),
            supports_open_slices: (bool) ($config['supports_open_slices'] ?? false),
            supports_delegated_spend: (bool) ($config['supports_delegated_spend'] ?? false),
            requires_envelope: (bool) ($config['requires_envelope'] ?? false),
            pay_code_route: (string) ($config['pay_code_route'] ?? 'disburse'),
            qr_type: (string) ($config['qr_type'] ?? 'claim'),
        );
    }

    public function typeOf(Voucher $voucher): VoucherFlowType
    {
        return VoucherFlowType::normalize(
            $this->rawFlowType($voucher),
            (string) config('x-change.voucher_flow_types.default', 'disbursable'),
        );
    }

    public function canDisburse(Voucher $voucher): bool
    {
        return $this->resolve($voucher)->can_disburse;
    }

    public function canCollect(Voucher $voucher): bool
    {
        return $this->resolve($voucher)->can_collect;
    }

    public function canSettle(Voucher $voucher): bool
    {
        return $this->resolve($voucher)->can_settle;
    }

    protected function rawFlowType(Voucher $voucher): ?string
    {
        foreach ($this->flowTypeCandidates($voucher) as $value) {
            $normalized = $this->normalizeRawValue($value);

            if ($normalized !== null) {
                return $normalized;
            }
        }

        return null;
    }

    /**
     * @return array<int, mixed>
     */
    protected function flowTypeCandidates(Voucher $voucher): array
    {
        $metadata = $voucher->getAttribute('metadata');
        $meta = $voucher->getAttribute('meta');

        return [
            // Highest priority: explicit instruction-level flow declaration.
            $this->instructionMetadataFlowType($voucher),
            data_get($metadata, 'instructions.metadata.flow_type'),
            data_get($metadata, 'instructions.rules.metadata.flow_type'),
            data_get($metadata, 'instructions.flow_type'),
            data_get($metadata, 'rules.metadata.flow_type'),
            data_get($metadata, 'rules.flow_type'),

            // Direct model / metadata flow declarations.
            $voucher->getAttribute('flow_type'),
            $voucher->getAttribute('voucher_flow_type'),
            data_get($metadata, 'flow_type'),
            data_get($metadata, 'voucher_flow_type'),
            data_get($meta, 'flow_type'),
            data_get($meta, 'voucher_flow_type'),

            // Legacy voucher type fallback.
            $voucher->getAttribute('voucher_type'),
            $this->instructionVoucherType($voucher),
            data_get($metadata, 'instructions.voucher_type'),
            data_get($metadata, 'voucher_type'),
            data_get($meta, 'voucher_type'),
        ];
    }

    protected function normalizeRawValue(mixed $value): ?string
    {
        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }

        return null;
    }

    protected function instructionMetadataFlowType(Voucher $voucher): mixed
    {
        try {
            return data_get($voucher->instructions?->metadata, 'flow_type');
        } catch (\Throwable) {
            return null;
        }
    }

    protected function instructionVoucherType(Voucher $voucher): mixed
    {
        try {
            return $voucher->instructions?->voucher_type ?? null;
        } catch (\Throwable) {
            return null;
        }
    }
}
