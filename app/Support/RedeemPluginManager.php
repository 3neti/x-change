<?php

namespace App\Support;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Arr;

class RedeemPluginManager
{
    public static function all(): array
    {
        return Config::get('x-change.redeem.plugins', []);
    }

    public static function enabled(): array
    {
        return collect(self::all())
            ->filter(fn ($plugin) => Arr::get($plugin, 'enabled', false))
            ->all();
    }

    public static function get(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }

    public static function getValidationRules(string $key): array
    {
        return Arr::get(self::get($key), 'validation', []);
    }

    public static function getSessionKey(string $key): ?string
    {
        return Arr::get(self::get($key), 'session_key');
    }

    public static function getMiddleware(string $key): ?string
    {
        return Arr::get(self::get($key), 'middleware');
    }

    public static function getPage(string $key): ?string
    {
        return Arr::get(self::get($key), 'page');
    }

    public static function getRoute(string $key): ?string
    {
        return Arr::get(self::get($key), 'route');
    }
}
