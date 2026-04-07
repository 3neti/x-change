<?php

declare(strict_types=1);

use LBHurtado\XChange\Actions\Onboarding\OpenIssuerWallet;
use LBHurtado\XChange\Contracts\IssuerResolverContract;
use LBHurtado\XChange\Contracts\WalletProvisioningContract;
use LBHurtado\XChange\Data\Onboarding\OpenIssuerWalletResultData;

it('opens issuer wallet through provisioning contract and returns normalized wallet payload', function () {
    $input = validOpenIssuerWalletPayload(1);

    $issuer = (object) [
        'id' => 1,
    ];

    $wallet = (object) [
        'id' => 10,
        'slug' => 'platform',
        'name' => 'Platform Wallet',
        'balance' => 0,
    ];

    $resolver = Mockery::mock(IssuerResolverContract::class);
    $resolver->shouldReceive('resolve')
        ->once()
        ->with($input)
        ->andReturn($issuer);

    $wallets = Mockery::mock(WalletProvisioningContract::class);
    $wallets->shouldReceive('open')
        ->once()
        ->with($issuer, $input)
        ->andReturn($wallet);

    $action = new OpenIssuerWallet($resolver, $wallets);

    $result = $action->handle($input);

    expect($result)->toBeInstanceOf(OpenIssuerWalletResultData::class);
    expect($result->issuer->id)->toBe(1);
    expect($result->wallet->id)->toBe(10);
    expect($result->wallet->slug)->toBe('platform');
    expect($result->wallet->name)->toBe('Platform Wallet');
    expect($result->wallet->balance)->toBe(0);
});

it('throws when issuer cannot be resolved', function () {
    $input = validOpenIssuerWalletPayload(999);

    $resolver = Mockery::mock(IssuerResolverContract::class);
    $resolver->shouldReceive('resolve')
        ->once()
        ->with($input)
        ->andReturn(null);

    $wallets = Mockery::mock(WalletProvisioningContract::class);
    $wallets->shouldNotReceive('open');

    $action = new OpenIssuerWallet($resolver, $wallets);

    expect(fn () => $action->handle($input))
        ->toThrow(RuntimeException::class, 'Issuer could not be resolved.');
});
