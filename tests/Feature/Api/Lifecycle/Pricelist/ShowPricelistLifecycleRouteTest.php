<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\PricelistServiceContract;

it('shows pricelist through the lifecycle route surface', function () {
    $result = [
        'name' => 'Default Pricelist',
        'currency' => 'PHP',
        'items' => [
            [
                'code' => 'base_fee',
                'name' => 'Base Fee',
                'category' => 'base',
                'amount' => 0.0,
                'amount_minor' => 0,
                'currency' => 'PHP',
                'active' => true,
            ],
            [
                'code' => 'cash',
                'name' => 'Cash',
                'category' => 'component',
                'amount' => 10.0,
                'amount_minor' => 1000,
                'currency' => 'PHP',
                'active' => true,
            ],
        ],
        'commercial_offering' => [
            'reference' => 'commercial-offering:pay_code',
            'version' => 4,
            'snapshot_hash' => str_repeat('a', 64),
            'effective_at' => '2026-08-16T00:00:00+00:00',
        ],
        'catalog' => [
            'reference' => 'pay-code',
            'version' => 8,
        ],
    ];

    $service = Mockery::mock(PricelistServiceContract::class);
    $service->shouldReceive('showPricelist')
        ->once()
        ->andReturn($result);

    $this->app->instance(PricelistServiceContract::class, $service);

    $response = $this->getJson('/api/x/v1/pricelist');

    $response
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.name', 'Default Pricelist')
        ->assertJsonPath('data.currency', 'PHP')
        ->assertJsonPath('data.items.0.code', 'base_fee')
        ->assertJsonPath('data.items.0.amount_minor', 0)
        ->assertJsonPath('data.items.1.code', 'cash')
        ->assertJsonPath('data.commercial_offering.reference', 'commercial-offering:pay_code')
        ->assertJsonPath('data.commercial_offering.version', 4)
        ->assertJsonPath('data.catalog.version', 8);
});
