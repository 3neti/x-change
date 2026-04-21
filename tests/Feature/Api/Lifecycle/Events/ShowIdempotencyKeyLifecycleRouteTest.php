<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\EventLifecycleServiceContract;

it('shows idempotency key details through the lifecycle route surface', function () {
    $result = [
        'key' => 'idem-001',
        'replayed' => true,
        'first_seen_at' => '2026-04-22T10:00:00+00:00',
        'last_seen_at' => '2026-04-22T10:05:00+00:00',
        'request_fingerprint' => 'fp-001',
        'response_status' => 200,
    ];

    $service = Mockery::mock(EventLifecycleServiceContract::class);
    $service->shouldReceive('showIdempotencyKey')
        ->once()
        ->with('idem-001')
        ->andReturn($result);

    $this->app->instance(EventLifecycleServiceContract::class, $service);

    $response = $this->getJson('/api/x/v1/events/idempotency/idem-001');

    $response
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.idempotency.key', 'idem-001')
        ->assertJsonPath('data.idempotency.replayed', true)
        ->assertJsonPath('data.idempotency.response_status', 200);
});
