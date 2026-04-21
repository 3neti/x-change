<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\EventLifecycleServiceContract;

it('shows an event through the lifecycle route surface', function () {
    $result = [
        'id' => 'evt-001',
        'type' => 'voucher.created',
        'status' => 'recorded',
        'actor' => 'system',
        'resource_type' => 'voucher',
        'resource_id' => '99',
        'correlation_id' => 'corr-001',
        'idempotency_key' => 'idem-001',
        'occurred_at' => '2026-04-22T10:00:00+00:00',
        'payload' => [
            'voucher_code' => 'TEST-1234',
        ],
    ];

    $service = Mockery::mock(EventLifecycleServiceContract::class);
    $service->shouldReceive('show')
        ->once()
        ->with('evt-001')
        ->andReturn($result);

    $this->app->instance(EventLifecycleServiceContract::class, $service);

    $response = $this->getJson('/api/x/v1/events/evt-001');

    $response
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.event.id', 'evt-001')
        ->assertJsonPath('data.event.type', 'voucher.created')
        ->assertJsonPath('data.event.payload.voucher_code', 'TEST-1234');
});
