<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\ReconciliationLifecycleServiceContract;

it('resolves a reconciliation through the lifecycle route surface', function () {
    $payload = [
        'resolution' => 'manual_clear',
        'notes' => 'Reviewed and cleared.',
        'metadata' => [],
    ];

    $result = [
        'reconciliation_id' => 'rec-001',
        'status' => 'resolved',
        'resolution' => 'manual_clear',
        'resolved' => true,
        'notes' => 'Reviewed and cleared.',
        'messages' => ['Reconciliation resolved successfully.'],
    ];

    $service = Mockery::mock(ReconciliationLifecycleServiceContract::class);
    $service->shouldReceive('resolve')
        ->once()
        ->with('rec-001', $payload)
        ->andReturn($result);

    $this->app->instance(ReconciliationLifecycleServiceContract::class, $service);

    $response = $this->postJson('/api/x/v1/reconciliations/rec-001/resolve', $payload);

    $response
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.reconciliation_id', 'rec-001')
        ->assertJsonPath('data.status', 'resolved')
        ->assertJsonPath('data.resolution', 'manual_clear')
        ->assertJsonPath('data.resolved', true);
});

it('validates required payload fields for reconciliation resolution through the lifecycle route surface', function () {
    $response = $this->postJson('/api/x/v1/reconciliations/rec-001/resolve', []);

    $response
        ->assertUnprocessable()
        ->assertJson([
            'success' => false,
            'code' => 'VALIDATION_ERROR',
        ]);
});
