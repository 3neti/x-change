<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\MinimumWithdrawalPolicyResolverContract;
use LBHurtado\XChange\Providers\XChangeServiceProvider;
use LBHurtado\XChange\Services\ConfigMinimumWithdrawalPolicyResolver;

it('resolves the minimum withdrawal policy resolver contract', function () {
    expect(app(MinimumWithdrawalPolicyResolverContract::class))
        ->toBeInstanceOf(ConfigMinimumWithdrawalPolicyResolver::class);
});

it('registers a fallback binding when published host config is stale', function () {
    config()->set('x-change.services', []);
    config()->set('x-change.service_contracts', []);

    app()->offsetUnset('x-change.services.minimum_withdrawal_policy');
    app()->offsetUnset(MinimumWithdrawalPolicyResolverContract::class);
    app()->forgetInstance('x-change.services.minimum_withdrawal_policy');
    app()->forgetInstance(MinimumWithdrawalPolicyResolverContract::class);

    $method = new ReflectionMethod(XChangeServiceProvider::class, 'registerMinimumWithdrawalPolicyResolver');
    $method->setAccessible(true);
    $method->invoke(new XChangeServiceProvider(app()));

    expect(app(MinimumWithdrawalPolicyResolverContract::class))
        ->toBeInstanceOf(ConfigMinimumWithdrawalPolicyResolver::class);
});
