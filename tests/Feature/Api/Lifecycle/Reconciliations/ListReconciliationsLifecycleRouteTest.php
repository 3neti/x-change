<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\ReconciliationLifecycleServiceContract;

it('lists reconciliations through the lifecycle route surface', function () {
    $result = [
        [
            'id' => 'rec-001',
            'reference' => 'rec-001',
            'status' => 'pending_review',
            'provider_status' => 'failed',
            'amount' => 100.00,
            'currency' => 'PHP',
        ],
    ];

    $service = Mockery::mock(ReconciliationLifecycleServiceContract::class);
    $service->shouldReceive('list')
        ->once()
        ->with([])
        ->andReturn($result);

    $this->app->instance(ReconciliationLifecycleServiceContract::class, $service);

    $response = $this->getJson('/api/x/v1/reconciliations');

    $response
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.items.0.id', 'rec-001')
        ->assertJsonPath('data.items.0.status', 'pending_review');
});
