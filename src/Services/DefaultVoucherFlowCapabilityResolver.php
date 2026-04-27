<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\VoucherFlowCapabilityResolverContract;
use LBHurtado\XChange\Data\VoucherFlow\VoucherFlowCapabilitiesData;
use LBHurtado\XChange\Enums\VoucherFlowType;

/**
 * Voucher Flow Capability System
 *
 * ------------------------------------------------------------------------
 * Overview
 * ------------------------------------------------------------------------
 *
 * The Voucher Flow Capability System defines how a voucher behaves
 * based on its canonical "flow type".
 *
 * Instead of hardcoding behavior across the system (e.g. "if redeemable...",
 * "if payable..."), this system centralizes behavior into a single,
 * config-driven capability model.
 *
 * This allows the platform to support multiple financial flows:
 *
 * - Disbursable  → outbound money (cash-out, payouts, withdrawals)
 * - Collectible  → inbound money (payments, top-ups, collections)
 * - Settlement   → bi-directional, policy-driven flows (loans, insurance, escrow)
 *
 *
 * ------------------------------------------------------------------------
 * Key Concepts
 * ------------------------------------------------------------------------
 *
 * 1. VoucherFlowType (Enum)
 *
 * Defines the canonical flow classification of a voucher:
 *
 * - disbursable
 * - collectible
 * - settlement
 *
 * Legacy values (e.g. "redeemable", "payable") are mapped to these
 * canonical types via config aliases.
 *
 *
 * 2. Capabilities (DTO)
 *
 * Each flow type resolves into a set of explicit capabilities:
 *
 * - can_disburse      → allows outward money movement
 * - can_collect       → allows inward money movement
 * - can_settle        → allows both directions under rules
 * - requires_envelope → requires settlement envelope readiness
 * - supports_slicing  → allows partial claims (open / fixed slices)
 *
 * These capabilities are returned as a strongly-typed DTO:
 *
 *     VoucherFlowCapabilitiesData
 *
 *
 * 3. Resolver (this class)
 *
 * This service:
 *
 * - Determines the canonical flow type of a voucher
 * - Applies alias normalization
 * - Falls back to default config when unspecified
 * - Returns the resolved capabilities for that voucher
 *
 *
 * ------------------------------------------------------------------------
 * Design Principles
 * ------------------------------------------------------------------------
 *
 * 1. Behavior is Config-Driven
 *
 * All flow definitions and capabilities are defined in:
 *
 *     config('x-change.voucher_flow_types')
 *
 * This allows new behaviors to be introduced without changing code.
 *
 *
 * 2. Type ≠ Behavior
 *
 * A voucher type defines a capability envelope, not exact behavior.
 *
 * Example:
 * - "settlement" allows both collect + disburse
 * - but actual execution may still depend on:
 *     - authorization policy
 *     - envelope readiness
 *     - lifecycle stage
 *
 *
 * 3. Backward Compatibility
 *
 * Legacy voucher types are supported via alias mapping:
 *
 *     redeemable → disbursable
 *     payable    → collectible
 *
 * This ensures existing systems continue to function during migration.
 *
 *
 * 4. Single Source of Truth
 *
 * All voucher behavior decisions SHOULD flow through this resolver.
 *
 * Avoid scattering logic like:
 *
 *     if ($voucher->isRedeemable()) ...
 *
 * Instead:
 *
 *     $capabilities = $resolver->resolve($voucher);
 *
 *
 * ------------------------------------------------------------------------
 * Usage
 * ------------------------------------------------------------------------
 *
 * Resolve capabilities:
 *
 *     $capabilities = app(VoucherFlowCapabilityResolverContract::class)
 *         ->resolve($voucher);
 *
 * Example:
 *
 *     if ($capabilities->can_disburse) {
 *         // allow withdrawal
 *     }
 *
 *
 * ------------------------------------------------------------------------
 * Future Extensions
 * ------------------------------------------------------------------------
 *
 * This system is designed to support:
 *
 * - Authorization policies (OTP, delegated spend, thresholds)
 * - Settlement modes (disburse-then-collect, escrow, insurance)
 * - Stage-based voucher lifecycles
 * - Envelope-gated execution
 *
 * These features will build on top of this capability layer.
 *
 *
 * ------------------------------------------------------------------------
 * Summary
 * ------------------------------------------------------------------------
 *
 * This system transforms vouchers from simple "types" into
 * programmable financial objects with explicit capabilities.
 *
 * It is the foundation for:
 *
 * - unified voucher flows
 * - consistent behavior across disbursement, payment, and settlement
 * - extensible financial orchestration
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
        foreach ([
            'flow_type',
            'voucher_flow_type',
            'voucher_type',
        ] as $attribute) {
            $value = $voucher->getAttribute($attribute);

            if ($value instanceof \BackedEnum) {
                return (string) $value->value;
            }

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        try {
            $value = $voucher->instructions?->voucher_type ?? null;

            if ($value instanceof \BackedEnum) {
                return (string) $value->value;
            }

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        } catch (\Throwable) {
            //
        }

        foreach ([
            'metadata.instructions.voucher_type',
            'metadata.flow_type',
            'metadata.voucher_flow_type',
            'metadata.voucher_type',
            'meta.flow_type',
            'meta.voucher_flow_type',
            'meta.voucher_type',
        ] as $path) {
            $value = data_get($voucher, $path);

            if ($value instanceof \BackedEnum) {
                return (string) $value->value;
            }

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    protected function rawTypeFrom(Voucher $voucher): ?string
    {
        $modelType = $voucher->getAttribute('voucher_type');

        if ($modelType instanceof \BackedEnum) {
            return (string) $modelType->value;
        }

        if (is_string($modelType) && $modelType !== '') {
            return $modelType;
        }

        try {
            $instructionType = $voucher->instructions?->voucher_type ?? null;

            if ($instructionType instanceof \BackedEnum) {
                return (string) $instructionType->value;
            }

            if (is_string($instructionType) && $instructionType !== '') {
                return $instructionType;
            }
        } catch (\Throwable) {
            // Ignore malformed/legacy instruction payloads and fall back below.
        }

        return data_get($voucher->getAttribute('metadata'), 'instructions.voucher_type')
            ?? data_get($voucher->getAttribute('metadata'), 'voucher_type')
            ?? data_get($voucher->getAttribute('meta'), 'voucher_type');
    }
}
