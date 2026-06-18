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

it('infers NetBank runtime provider from an explicit payout provider hint when default remains manual', function () {
    config()->set('x-change.provider_runtime.default_provider', 'manual');
    config()->set('x-change.provider_runtime.payout_provider_hint', 'LBHurtado\PaymentGateway\Adapters\NetbankPayoutProvider');

    $settings = app(ProviderRuntimeSettingsResolverContract::class);

    expect($settings->provider())->toBe('netbank')
        ->and($settings->topology())->toBe('ledger_pooled');
});

it('does not infer a provider from an empty payout provider hint', function () {
    config()->set('x-change.provider_runtime.default_provider', 'manual');
    config()->set('x-change.provider_runtime.payout_provider_hint', null);

    expect(app(ProviderRuntimeSettingsResolverContract::class)->provider())->toBe('manual');
});
