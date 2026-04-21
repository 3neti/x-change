<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\ReconciliationLifecycleServiceContract;

it('shows a reconciliation through the lifecycle route surface', function () {
    $result = [
        'id' => 'rec-001',
        'reference' => 'rec-001',
        'status' => 'pending_review',
        'provider_status' => 'failed',
        'amount' => 100.00,
        'currency' => 'PHP',
        'reason' => 'Provider mismatch',
        'resolved' => false,
        'resolved_at' => null,
    ];

    $service = Mockery::mock(ReconciliationLifecycleServiceContract::class);
    $service->shouldReceive('show')
        ->once()
        ->with('rec-001')
        ->andReturn($result);

    $this->app->instance(ReconciliationLifecycleServiceContract::class, $service);

    $response = $this->getJson('/api/x/v1/reconciliations/rec-001');

    $response
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.reconciliation.id', 'rec-001')
        ->assertJsonPath('data.reconciliation.status', 'pending_review');
});
