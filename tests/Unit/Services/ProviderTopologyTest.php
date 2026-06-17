<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\WalletAccessContract;
use LBHurtado\XChange\Contracts\WalletProvisioningContract;
use LBHurtado\XChange\Contracts\XChangeProviderTopologyContract;
use LBHurtado\XChange\Contracts\XChangeProviderTopologyResolverContract;
use LBHurtado\XChange\Services\LedgerPooledProviderTopology;
use LBHurtado\XChange\Services\ManualProviderTopology;
use LBHurtado\XChange\Services\ProviderCustomerWalletTopology;

it('resolves the configured manual provider topology by default', function () {
    config()->set('x-change.provider_topologies.default', 'manual');

    $topology = app(XChangeProviderTopologyResolverContract::class)->resolve();

    expect($topology)->toBeInstanceOf(XChangeProviderTopologyContract::class)
        ->and($topology)->toBeInstanceOf(ManualProviderTopology::class)
        ->and($topology->key())->toBe('manual')
        ->and($topology->requiresProviderCredentialsPerUser())->toBeFalse()
        ->and($topology->usesLocalLedgerAsSourceOfTruth())->toBeTrue();
});

it('maps netbank to the ledger pooled topology', function () {
    $wallet = (object) ['id' => 10, 'slug' => 'platform'];
    $issuer = (object) ['id' => 5];

    $provisioning = Mockery::mock(WalletProvisioningContract::class);
    $provisioning->shouldReceive('open')
        ->once()
        ->with($issuer, ['provider' => 'netbank'])
        ->andReturn($wallet);

    $wallets = Mockery::mock(WalletAccessContract::class);
    $wallets->shouldReceive('resolveForUser')
        ->once()
        ->with($issuer)
        ->andReturn($wallet);

    app()->instance(WalletProvisioningContract::class, $provisioning);
    app()->instance(WalletAccessContract::class, $wallets);

    $topology = app(XChangeProviderTopologyResolverContract::class)->resolve('netbank');

    expect($topology)->toBeInstanceOf(LedgerPooledProviderTopology::class)
        ->and($topology->key())->toBe('ledger_pooled')
        ->and($topology->requiresProviderCredentialsPerUser())->toBeFalse()
        ->and($topology->usesLocalLedgerAsSourceOfTruth())->toBeTrue()
        ->and($topology->provisionIssuer($issuer, ['provider' => 'netbank']))->toBe($wallet)
        ->and($topology->resolveFundingSource($issuer))->toBe($wallet);
});

it('maps paynamics to provider customer wallet topology', function () {
    $issuer = (object) [
        'id' => 7,
        'paynamics_customer_wallet_id' => 'CNSTWLLT-CUSTOMER',
    ];

    $topology = app(XChangeProviderTopologyResolverContract::class)->resolve('paynamics');

    expect($topology)->toBeInstanceOf(ProviderCustomerWalletTopology::class)
        ->and($topology->key())->toBe('provider_customer_wallet')
        ->and($topology->requiresProviderCredentialsPerUser())->toBeTrue()
        ->and($topology->usesLocalLedgerAsSourceOfTruth())->toBeFalse()
        ->and($topology->resolveFundingSource($issuer, ['provider' => 'paynamics']))->toMatchArray([
            'topology' => 'provider_customer_wallet',
            'issuer_id' => 7,
            'provider' => 'paynamics',
            'customer_wallet_id' => 'CNSTWLLT-CUSTOMER',
            'source' => 'provider_customer_wallet',
        ]);
});
