<?php

declare(strict_types=1);

use LBHurtado\XChange\Actions\Onboarding\OpenIssuerWallet;
use LBHurtado\XChange\Contracts\UserResolverContract;
use LBHurtado\XChange\Contracts\WalletProvisioningContract;

it('opens issuer wallet through provisioning contract and returns normalized wallet payload', function () {
    $input = [
        'issuer_id' => 1,
        'wallet' => [
            'slug' => 'platform',
            'name' => 'Platform Wallet',
        ],
        'metadata' => [],
    ];

    $issuer = (object) [
        'id' => 1,
    ];

    $wallet = (object) [
        'id' => 10,
        'slug' => 'platform',
        'name' => 'Platform Wallet',
        'balance' => 0,
    ];

    $users = Mockery::mock(UserResolverContract::class);
    $users->shouldReceive('resolve')
        ->once()
        ->with($input)
        ->andReturn($issuer);

    $wallets = Mockery::mock(WalletProvisioningContract::class);
    $wallets->shouldReceive('open')
        ->once()
        ->with($issuer, $input)
        ->andReturn($wallet);

    $action = new OpenIssuerWallet($users, $wallets);

    $result = $action->handle($input);

    expect($result)->toBe([
        'issuer' => [
            'id' => 1,
        ],
        'wallet' => [
            'id' => 10,
            'slug' => 'platform',
            'name' => 'Platform Wallet',
            'balance' => 0,
        ],
    ]);
});
