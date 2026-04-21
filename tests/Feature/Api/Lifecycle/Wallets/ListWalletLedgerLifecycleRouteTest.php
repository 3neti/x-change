<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\WalletAccessContract;

it('lists wallet ledger through the lifecycle route surface', function () {
    $result = collect([
        (object) [
            'id' => 1,
            'type' => 'credit',
            'amount' => 1000.00,
            'currency' => 'PHP',
            'reference' => 'TOPUP-001',
            'created_at' => '2026-04-21T10:00:00Z',
        ],
    ]);

    $service = Mockery::mock(WalletAccessContract::class);
    $service->shouldReceive('ledger')
        ->once()
        ->with('platform', [])
        ->andReturn($result);

    $this->app->instance(WalletAccessContract::class, $service);

    $response = $this->getJson('/api/x/v1/wallets/platform/ledger');

    $response->assertJson(fn ($json) => $json->where('success', true)
        ->where('data.items.0.id', 1)
        ->where('data.items.0.type', 'credit')
        ->where('data.items.0.amount', 1000)
        ->etc()
    );
});
