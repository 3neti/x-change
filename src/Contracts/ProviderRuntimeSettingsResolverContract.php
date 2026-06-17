<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

interface ProviderRuntimeSettingsResolverContract
{
    public function provider(?string $override = null): string;

    public function topology(?string $provider = null): string;

    public function isEnabled(string $provider): bool;

    public function allowsLiveProviderScenarios(): bool;

    public function setting(string $key, mixed $default = null): mixed;
}
