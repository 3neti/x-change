<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\ProviderRuntimeSettingsResolverContract;

it('resolves provider runtime settings from config without exposing secrets', function () {
    config()->set('x-change.provider_runtime.default_provider', 'paynamics');
    config()->set('x-change.provider_runtime.providers.paynamics.enabled', true);
    config()->set('x-change.provider_runtime.lifecycle.allow_live_provider_scenarios', false);

    $settings = app(ProviderRuntimeSettingsResolverContract::class);

    expect($settings->provider())->toBe('paynamics')
        ->and($settings->topology())->toBe('provider_customer_wallet')
        ->and($settings->isEnabled('paynamics'))->toBeTrue()
        ->and($settings->allowsLiveProviderScenarios())->toBeFalse()
        ->and($settings->setting('providers.paynamics.enabled'))->toBeTrue()
        ->and($settings->setting('password'))->toBeNull();
});
