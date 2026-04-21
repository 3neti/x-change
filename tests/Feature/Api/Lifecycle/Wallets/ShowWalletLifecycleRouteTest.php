<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\WalletAccessContract;

it('shows a wallet through the lifecycle route surface', function () {
    $result = (object) [
        'id' => 10,
        'slug' => 'platform',
        'name' => 'Platform Wallet',
        'balance' => 1000.00,
        'currency' => 'PHP',
    ];

    $service = Mockery::mock(WalletAccessContract::class);
    $service->shouldReceive('find')
        ->once()
        ->with('platform')
        ->andReturn($result);

    $this->app->instance(WalletAccessContract::class, $service);

    $response = $this->getJson('/api/x/v1/wallets/platform');

    $response
        ->assertOk()
        ->assertJson([
            'success' => true,
            'data' => [
                'wallet' => [
                    'id' => 10,
                    'slug' => 'platform',
                    'name' => 'Platform Wallet',
                    'balance' => 1000.0,
                    'currency' => 'PHP',
                ],
            ],
            'meta' => [],
        ]);
});
