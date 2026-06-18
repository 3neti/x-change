<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\XChange\Contracts\ProviderRuntimeSettingsResolverContract;
use LBHurtado\XChange\Contracts\XChangeProviderTopologyResolverContract;

class ConfigProviderRuntimeSettingsResolver implements ProviderRuntimeSettingsResolverContract
{
    public function __construct(
        protected XChangeProviderTopologyResolverContract $topologies,
    ) {}

    public function provider(?string $override = null): string
    {
        $provider = $override
            ?? config('x-change.provider_runtime.default_provider')
            ?? config('x-change.provider_topologies.default', 'manual');

        $provider = strtolower((string) $provider);

        if ($override === null && $provider === 'manual') {
            return $this->providerFromExplicitPayoutHint() ?? $provider;
        }

        return $provider;
    }

    public function topology(?string $provider = null): string
    {
        return $this->topologies->resolve($provider ?? $this->provider())->key();
    }

    public function isEnabled(string $provider): bool
    {
        $provider = strtolower($provider);

        return (bool) config("x-change.provider_runtime.providers.{$provider}.enabled", true);
    }

    public function allowsLiveProviderScenarios(): bool
    {
        return (bool) config('x-change.provider_runtime.lifecycle.allow_live_provider_scenarios', false);
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return config("x-change.provider_runtime.{$key}", $default);
    }

    protected function providerFromExplicitPayoutHint(): ?string
    {
        $provider = config('x-change.provider_runtime.payout_provider_hint');

        if (! is_string($provider) || trim($provider) === '') {
            return null;
        }

        $provider = strtolower($provider);

        return match (true) {
            str_contains($provider, 'netbank') => 'netbank',
            str_contains($provider, 'paynamics'), str_contains($provider, 'constellation') => 'paynamics',
            default => null,
        };
    }
}
