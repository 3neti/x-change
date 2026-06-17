<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Support\Auth;

class MobileNumber
{
    public static function normalize(?string $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value);

        if (! is_string($digits) || $digits === '') {
            return null;
        }

        if (str_starts_with($digits, '09') && strlen($digits) === 11) {
            return '63'.substr($digits, 1);
        }

        if (str_starts_with($digits, '9') && strlen($digits) === 10) {
            return '63'.$digits;
        }

        if (str_starts_with($digits, '639') && strlen($digits) === 12) {
            return $digits;
        }

        return $digits;
    }

    /**
     * @return array<int, string>
     */
    public static function candidates(?string $value): array
    {
        $normalized = self::normalize($value);

        if (! is_string($normalized) || $normalized === '') {
            return [];
        }

        $candidates = [$normalized];

        if (str_starts_with($normalized, '63') && strlen($normalized) === 12) {
            $candidates[] = '0'.substr($normalized, 2);
            $candidates[] = substr($normalized, 2);
        }

        return array_values(array_unique($candidates));
    }
}
