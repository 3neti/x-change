<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Enums;

use InvalidArgumentException;

/**
 * VoucherFlowType
 *
 * ------------------------------------------------------------------------
 * Overview
 * ------------------------------------------------------------------------
 *
 * Defines the canonical flow classification of a voucher.
 *
 * These types represent the *capability envelope* of a voucher,
 * not its exact runtime behavior.
 *
 *
 * ------------------------------------------------------------------------
 * Canonical Types
 * ------------------------------------------------------------------------
 *
 * - disbursable  → outbound money (cash-out, payouts, withdrawals)
 * - collectible  → inbound money (payments, top-ups, collections)
 * - settlement   → bi-directional, policy-driven flows
 *
 *
 * ------------------------------------------------------------------------
 * Important Notes
 * ------------------------------------------------------------------------
 *
 * - This enum replaces legacy voucher types such as:
 *     - redeemable → disbursable
 *     - payable    → collectible
 *
 * - Actual behavior (e.g. whether a voucher can disburse or collect)
 *   is determined by the Voucher Flow Capability Resolver,
 *   not by this enum alone.
 *
 * - Always resolve capabilities via:
 *
 *     VoucherFlowCapabilityResolverContract
 *
 *   instead of branching directly on this enum.
 *
 *
 * ------------------------------------------------------------------------
 * Design Intent
 * ------------------------------------------------------------------------
 *
 * This enum provides a stable, normalized vocabulary for voucher flows,
 * enabling:
 *
 * - consistent behavior across the system
 * - backward compatibility via alias mapping
 * - future extensibility through config-driven capabilities
 */
enum VoucherFlowType: string
{
    case Disbursable = 'disbursable';
    case Collectible = 'collectible';
    case Settlement = 'settlement';

    public static function normalize(?string $value, ?string $default = null): self
    {
        $value = trim((string) ($value ?: $default ?: 'disbursable'));

        $aliases = (array) config('x-change.voucher_flow_types.aliases', [
            'redeemable' => 'disbursable',
            'payable' => 'collectible',
        ]);

        $value = (string) ($aliases[$value] ?? $value);

        return self::tryFrom($value)
            ?? throw new InvalidArgumentException("Unsupported voucher flow type [{$value}].");
    }

    public function label(): string
    {
        return match ($this) {
            self::Disbursable => 'Cash Out Voucher',
            self::Collectible => 'Pay In Voucher',
            self::Settlement => 'Settlement Voucher',
        };
    }

    public function direction(): string
    {
        return match ($this) {
            self::Disbursable => 'outward',
            self::Collectible => 'inward',
            self::Settlement => 'bilateral',
        };
    }

    public function isDisbursable(): bool
    {
        return $this === self::Disbursable;
    }

    public function isCollectible(): bool
    {
        return $this === self::Collectible;
    }

    public function isSettlement(): bool
    {
        return $this === self::Settlement;
    }
}
