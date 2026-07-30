<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Support\Money;

use InvalidArgumentException;

final class MajorCurrencyAmount
{
    public static function toMinor(string $value, string $currency = 'PHP'): int
    {
        $normalized = trim(str_replace(["\u{00A0}", "\u{202F}"], ' ', $value));
        if (strtoupper($currency) === 'PHP') {
            $normalized = preg_replace('/^(?:PHP|₱)\s*/iu', '', $normalized) ?? $normalized;
        }

        $normalized = trim($normalized);
        if (preg_match('/^(?:\d+|\d{1,3}(?:,\d{3})+)(?:\.\d{1,2})?$/', $normalized) !== 1) {
            throw new InvalidArgumentException(
                'Enter a positive peso amount with no more than two decimal places.',
            );
        }

        [$whole, $fraction] = array_pad(
            explode('.', str_replace(',', '', $normalized), 2),
            2,
            '',
        );

        if (strlen($whole) > 13) {
            throw new InvalidArgumentException('The amount is too large.');
        }

        $minor = ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
        if ($minor < 1 || $minor > 999_999_999_999_999) {
            throw new InvalidArgumentException(
                'Enter an amount from ₱0.01 to ₱9,999,999,999,999.99.',
            );
        }

        return $minor;
    }
}
