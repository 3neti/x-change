<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\FundingDestinationResolverContract;
use LBHurtado\XChange\Providers\XChangeServiceProvider;
use LBHurtado\XChange\Services\Funding\DefaultFundingDestinationResolver;

it('resolves the funding destination resolver contract', function () {
    expect(app(FundingDestinationResolverContract::class))
        ->toBeInstanceOf(DefaultFundingDestinationResolver::class);
});

it('registers a fallback binding when published host config is stale', function () {
    config()->set('x-change.services', []);
    config()->set('x-change.service_contracts', []);

    app()->offsetUnset('x-change.services.funding_destination_resolver');
    app()->offsetUnset(FundingDestinationResolverContract::class);
    app()->forgetInstance('x-change.services.funding_destination_resolver');
    app()->forgetInstance(FundingDestinationResolverContract::class);

    $method = new ReflectionMethod(XChangeServiceProvider::class, 'registerFundingDestinationResolver');
    $method->setAccessible(true);
    $method->invoke(new XChangeServiceProvider(app()));

    expect(app(FundingDestinationResolverContract::class))
        ->toBeInstanceOf(DefaultFundingDestinationResolver::class);
});
