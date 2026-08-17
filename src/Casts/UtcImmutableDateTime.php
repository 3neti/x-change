<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Casts;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/** @implements CastsAttributes<CarbonImmutable|null, mixed> */
final class UtcImmutableDateTime implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?CarbonImmutable
    {
        return $value === null
            ? null
            : CarbonImmutable::parse((string) $value, 'UTC')->utc();
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null
            ? null
            : CarbonImmutable::parse($value)->utc()->format('Y-m-d H:i:s.u');
    }
}
