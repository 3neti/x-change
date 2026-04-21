<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\WalletAccessContract;

it('creates a wallet top up through the lifecycle route surface', function () {
    $payload = [
        'amount' => 500.00,
        'currency' => 'PHP',
        'reference' => 'TOPUP-002',
        'metadata' => [],
    ];

    $result = (object) [
        'id' => 55,
        'wallet_id' => 10,
        'amount' => 500.00,
        'currency' => 'PHP',
        'reference' => 'TOPUP-002',
        'status' => 'completed',
    ];

    $service = Mockery::mock(WalletAccessContract::class);
    $service->shouldReceive('topUp')
        ->once()
        ->with('platform', $payload)
        ->andReturn($result);

    $this->app->instance(WalletAccessContract::class, $service);

    $response = $this->postJson('/api/x/v1/wallets/platform/top-ups', $payload);

    $response
        ->assertCreated()
        ->assertJson([
            'success' => true,
            'data' => [
                'top_up' => [
                    'id' => 55,
                    'wallet_id' => 10,
                    'amount' => 500.0,
                    'currency' => 'PHP',
                    'reference' => 'TOPUP-002',
                    'status' => 'completed',
                ],
            ],
            'meta' => [],
        ]);
});

it('validates required payload fields for wallet top up through the lifecycle route surface', function () {
    $response = $this->postJson('/api/x/v1/wallets/platform/top-ups', []);

    $response
        ->assertUnprocessable()
        ->assertJson([
            'success' => false,
            'code' => 'VALIDATION_ERROR',
        ]);
});
