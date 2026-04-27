<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Enums;

use InvalidArgumentException;

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
}
