<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\PricelistServiceContract;

it('lists pricelist items through the lifecycle route surface', function () {
    $result = [
        [
            'code' => 'SERVICE_FEE',
            'name' => 'Service Fee',
            'category' => 'voucher',
            'amount' => 10.00,
            'currency' => 'PHP',
            'active' => true,
        ],
    ];

    $service = Mockery::mock(PricelistServiceContract::class);
    $service->shouldReceive('listItems')
        ->once()
        ->with([])
        ->andReturn($result);

    $this->app->instance(PricelistServiceContract::class, $service);

    $response = $this->getJson('/api/x/v1/pricelist/items');

    $response
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.items.0.code', 'SERVICE_FEE')
        ->assertJsonPath('data.items.0.name', 'Service Fee')
        ->assertJsonPath('data.items.0.category', 'voucher')
        ->assertJsonPath('data.items.0.amount', 10)
        ->assertJsonPath('data.items.0.currency', 'PHP')
        ->assertJsonPath('data.items.0.active', true);
});
