<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use InvalidArgumentException;

class PayoutProviderResolver
{
    public function resolve(mixed $provider): string
    {
        $provider = is_string($provider) ? trim($provider) : '';

        if ($provider === '') {
            throw new InvalidArgumentException('No x-change payout provider is configured.');
        }

        if (class_exists($provider)) {
            return $provider;
        }

        $alias = strtolower($provider);
        $class = config("emi.payout_providers.{$alias}");

        if (is_string($class) && class_exists($class)) {
            return $class;
        }

        $available = implode(', ', array_keys((array) config('emi.payout_providers', [])));

        throw new InvalidArgumentException(
            "Unknown x-change payout provider [{$provider}]. Use a class name or one of: {$available}."
        );
    }
}
