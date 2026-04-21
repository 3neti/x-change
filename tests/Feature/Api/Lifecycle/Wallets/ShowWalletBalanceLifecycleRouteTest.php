<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\WalletAccessContract;

it('shows wallet balance through the lifecycle route surface', function () {
    $result = (object) [
        'wallet_id' => 10,
        'balance' => 1000.00,
        'currency' => 'PHP',
    ];

    $service = Mockery::mock(WalletAccessContract::class);
    $service->shouldReceive('balance')
        ->once()
        ->with('platform')
        ->andReturn($result);

    $this->app->instance(WalletAccessContract::class, $service);

    $response = $this->getJson('/api/x/v1/wallets/platform/balance');

    $response
        ->assertOk()
        ->assertJson([
            'success' => true,
            'data' => [
                'wallet_id' => 10,
                'balance' => 1000.0,
                'currency' => 'PHP',
            ],
            'meta' => [],
        ]);
});
