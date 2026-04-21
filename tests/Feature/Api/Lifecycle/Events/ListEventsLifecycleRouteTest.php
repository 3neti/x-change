<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\EventLifecycleServiceContract;

it('lists events through the lifecycle route surface', function () {
    $result = [
        [
            'id' => 'evt-001',
            'type' => 'voucher.created',
            'status' => 'recorded',
            'resource_type' => 'voucher',
            'resource_id' => '99',
            'occurred_at' => '2026-04-22T10:00:00+00:00',
        ],
    ];

    $service = Mockery::mock(EventLifecycleServiceContract::class);
    $service->shouldReceive('list')
        ->once()
        ->with([])
        ->andReturn($result);

    $this->app->instance(EventLifecycleServiceContract::class, $service);

    $response = $this->getJson('/api/x/v1/events');

    $response
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.items.0.id', 'evt-001')
        ->assertJsonPath('data.items.0.type', 'voucher.created');
});
