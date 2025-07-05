<?php

namespace App\Support;

use Illuminate\Support\Facades\Config;

class RedeemPluginMap
{
    /**
     * Get input fields assigned to a plugin.
     */
    public static function fieldsFor(string $plugin): array
    {
        $config = Config::get("x-change.redeem.plugins.$plugin");

        if (! $config || ! ($config['enabled'] ?? false)) {
            return [];
        }

        return $config['fields'] ?? [];
    }

    /**
     * Return a list of all enabled plugin keys.
     */
    public static function allPlugins(): array
    {
        return collect(Config::get('x-change.redeem.plugins', []))
            ->filter(fn ($cfg) => $cfg['enabled'] ?? false)
            ->keys()
            ->toArray();
    }

    /**
     * Return the first enabled plugin, or null if none.
     */
    public static function firstPlugin(): ?string
    {
        return collect(Config::get('x-change.redeem.plugins', []))
            ->filter(fn ($cfg) => $cfg['enabled'] ?? false)
            ->keys()
            ->first();
    }

    /**
     * Return the next plugin in the sequence after the given plugin.
     */
    public static function nextPluginAfter(string $current): ?string
    {
        $plugins = collect(Config::get('x-change.redeem.plugins', []))
            ->filter(fn ($cfg) => $cfg['enabled'] ?? false)
            ->keys()
            ->values();

        $index = $plugins->search($current);

        return $plugins->get($index + 1);
    }
}
