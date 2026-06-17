<?php

declare(strict_types=1);

use LBHurtado\PaymentGateway\Contracts\PaymentGatewayInterface;
use LBHurtado\XChange\Contracts\WalletProvisioningContract;
use LBHurtado\XChange\Enums\ProviderProvisioningMode;
use LBHurtado\XChange\Services\Provisioning\NetbankProviderProvisioningGateway;

it('provisions NetBank bank-account readiness from collected destination details', function () {
    $wallets = Mockery::mock(WalletProvisioningContract::class);
    $wallets->shouldNotReceive('open');

    $gateway = new NetbankProviderProvisioningGateway(
        $wallets,
        Mockery::mock(PaymentGatewayInterface::class),
    );

    $result = $gateway->provision(new stdClass, [
        'provider' => 'netbank',
        'topology' => 'ledger_pooled',
        'mode' => ProviderProvisioningMode::BankAccountLink->value,
        'purpose' => 'BankOnboardingRequired',
        'bank_code' => 'GXCHPHM2XXX',
        'account_number_masked' => '*******1987',
    ]);

    expect($result)->toMatchArray([
        'provider' => 'netbank',
        'topology' => 'ledger_pooled',
        'mode' => ProviderProvisioningMode::BankAccountLink->value,
        'status' => 'ready',
        'verification_status' => 'APPROVED',
    ])
        ->and(data_get($result, 'provider_bank_account_id'))->toBe('NETBANK-GXCHPHM2XXX-XXXXXXX1987')
        ->and(data_get($result, 'metadata.bank_code'))->toBe('GXCHPHM2XXX')
        ->and(data_get($result, 'metadata.account_number_masked'))->toBe('*******1987');
});

it('provisions NetBank ledger-wallet readiness by opening the owner wallet', function () {
    $owner = new stdClass;
    $wallet = (object) [
        'id' => 55,
        'slug' => 'platform',
    ];

    $wallets = Mockery::mock(WalletProvisioningContract::class);
    $wallets->shouldReceive('open')
        ->once()
        ->with($owner, [
            'wallet' => [
                'slug' => 'platform',
                'name' => 'Platform Wallet',
            ],
        ])
        ->andReturn($wallet);

    $gateway = new NetbankProviderProvisioningGateway(
        $wallets,
        Mockery::mock(PaymentGatewayInterface::class),
    );

    $result = $gateway->provision($owner, [
        'provider' => 'netbank',
        'mode' => ProviderProvisioningMode::LedgerWallet->value,
        'purpose' => 'IssuePayCode',
    ]);

    expect($result)->toMatchArray([
        'provider' => 'netbank',
        'mode' => ProviderProvisioningMode::LedgerWallet->value,
        'status' => 'ready',
    ])
        ->and(data_get($result, 'metadata.wallet.id'))->toBe(55)
        ->and(data_get($result, 'metadata.wallet.slug'))->toBe('platform');
});

it('optionally records NetBank source-account readiness metadata', function () {
    config()->set('x-change.provider_runtime.providers.netbank.source_account_readiness.enabled', true);
    config()->set('x-change.provider_runtime.providers.netbank.source_account_readiness.account_number', '1234567890');

    $wallets = Mockery::mock(WalletProvisioningContract::class);
    $wallets->shouldNotReceive('open');

    $paymentGateway = Mockery::mock(PaymentGatewayInterface::class);
    $paymentGateway->shouldReceive('checkAccountBalance')
        ->once()
        ->with('1234567890')
        ->andReturn([
            'balance' => 500000,
            'available_balance' => 480000,
            'currency' => 'PHP',
            'raw' => [],
        ]);

    $gateway = new NetbankProviderProvisioningGateway($wallets, $paymentGateway);

    $result = $gateway->provision(new stdClass, [
        'provider' => 'netbank',
        'mode' => ProviderProvisioningMode::BankAccountLink->value,
        'bank_code' => 'GXCHPHM2XXX',
        'account_number_masked' => '*******1987',
    ]);

    expect(data_get($result, 'metadata.source_account'))->toMatchArray([
        'ready' => true,
        'checked' => true,
        'account_number_masked' => '******7890',
        'balance' => 500000,
        'available_balance' => 480000,
        'currency' => 'PHP',
    ]);
});
