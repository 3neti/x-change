<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Support\Time;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use InvalidArgumentException;

final class UtcInstant
{
    public static function parse(mixed $value): CarbonImmutable
    {
        return $value instanceof DateTimeInterface
            ? CarbonImmutable::instance($value)->utc()
            : CarbonImmutable::parse((string) $value)->utc();
    }

    public static function parseOffsetRequired(string $value): CarbonImmutable
    {
        $value = trim($value);

        if (! preg_match('/(?:Z|[+-]\d{2}:?\d{2})$/i', $value)) {
            throw new InvalidArgumentException('The instant must include Z or a numeric timezone offset.');
        }

        return self::parse($value);
    }

    public static function parseDateOrOffsetRequired(string $value): CarbonImmutable
    {
        $value = trim($value);

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $value, 'UTC');

            if ($date !== false && $date->format('Y-m-d') === $value) {
                return $date;
            }
        }

        return self::parseOffsetRequired($value);
    }

    public static function canonical(mixed $value): string
    {
        return self::parse($value)->format('Y-m-d\TH:i:s.u\Z');
    }
}
