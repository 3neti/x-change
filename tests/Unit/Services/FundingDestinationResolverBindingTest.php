<?php

declare(strict_types=1);

use LBHurtado\EmiCore\Contracts\ProviderFundingDestinationResolver;
use LBHurtado\EmiCore\Data\Funding\FundingDestinationData;
use LBHurtado\EmiCore\Data\Funding\ProviderFundingDestinationLinkData;
use LBHurtado\EmiCore\Exceptions\ProviderFundingDestinationUnavailable;
use LBHurtado\EmiPaynamicsConstellation\Adapters\ConstellationFundingProviderAdapter;
use LBHurtado\XChange\Contracts\FundingDestinationResolverContract;
use LBHurtado\XChange\Exceptions\FundingDestinationUnavailable;
use LBHurtado\XChange\Providers\XChangeServiceProvider;
use LBHurtado\XChange\Services\Funding\DefaultFundingDestinationResolver;

it('resolves the funding destination resolver contract', function () {
    expect(app(FundingDestinationResolverContract::class))
        ->toBeInstanceOf(DefaultFundingDestinationResolver::class);
});

it('resolves shared provider routing through each provider adapter', function () {
    config()->set('payment-gateway.netbank.funding.corporate_account_number', '113001000019');
    config()->set('payment-gateway.netbank.funding.corporate_account_name', 'Treasury');
    config()->set('payment-gateway.netbank.funding.vca_alias', '91500');
    config()->set('constellation.funding.wallet_id', 'wallet-123456');
    $resolver = app(DefaultFundingDestinationResolver::class);

    expect($resolver->shared('netbank', 'account-1')->displayReference)->toBe('•••• 0019 · VCA 91500')
        ->and($resolver->shared('paynamics', 'account-1')->providerWalletId)->toBe('wallet-123456');
});

it('blocks unverified dedicated wallet destinations in the provider', function () {
    $link = new ProviderFundingDestinationLinkData('unverified_manual_link', null, 'wallet-123', null, null, null);

    expect(fn () => app(ConstellationFundingProviderAdapter::class)->dedicatedFundingDestination($link, 'account-1'))
        ->toThrow(ProviderFundingDestinationUnavailable::class, 'ownership verification');
});

it('rejects a provider destination that changes the account reference', function () {
    $provider = new class implements ProviderFundingDestinationResolver
    {
        public function providerCode(): string
        {
            return 'other';
        }

        public function sharedFundingDestination(string $accountReference): FundingDestinationData
        {
            return new FundingDestinationData(provider: 'other', mode: 'shared', destinationType: 'wallet', accountReference: 'different-account', displayReference: 'masked', fingerprint: 'hash', verificationStatus: 'configured');
        }

        public function dedicatedFundingDestination(ProviderFundingDestinationLinkData $link, string $accountReference): FundingDestinationData
        {
            return $this->sharedFundingDestination($accountReference);
        }
    };
    $this->app->instance('test.mismatched-destination-provider', $provider);
    $this->app->tag('test.mismatched-destination-provider', 'emi.funding-destination-resolvers');

    expect(fn () => app(DefaultFundingDestinationResolver::class)->shared('other', 'account-1'))
        ->toThrow(FundingDestinationUnavailable::class, 'mismatched');
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
