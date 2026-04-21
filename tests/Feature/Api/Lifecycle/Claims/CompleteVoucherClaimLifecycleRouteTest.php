<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\RedemptionCompletionServiceContract;

it('completes a voucher claim through the lifecycle route surface', function () {
    $payload = [
        'reference_id' => 'CLAIM-REF-001',
        'status' => 'completed',
        'notes' => 'Completed successfully.',
        'metadata' => [],
    ];

    $result = (object) [
        'voucher_code' => 'TEST-1234',
        'reference_id' => 'CLAIM-REF-001',
        'status' => 'completed',
        'completed' => true,
        'notes' => 'Completed successfully.',
        'messages' => ['Claim completion recorded.'],
    ];

    $service = Mockery::mock(RedemptionCompletionServiceContract::class);
    $service->shouldReceive('complete')
        ->once()
        ->with('TEST-1234', $payload)
        ->andReturn($result);

    $this->app->instance(RedemptionCompletionServiceContract::class, $service);

    $response = $this->postJson('/api/x/v1/vouchers/code/TEST-1234/claim/complete', $payload);

    $response
        ->assertOk()
        ->assertJson([
            'success' => true,
            'data' => [
                'voucher_code' => 'TEST-1234',
                'reference_id' => 'CLAIM-REF-001',
                'status' => 'completed',
                'completed' => true,
                'notes' => 'Completed successfully.',
                'messages' => ['Claim completion recorded.'],
            ],
            'meta' => [],
        ]);
});

it('validates required payload fields for claim completion through the lifecycle route surface', function () {
    $response = $this->postJson('/api/x/v1/vouchers/code/TEST-1234/claim/complete', []);

    $response
        ->assertUnprocessable()
        ->assertJson([
            'success' => false,
            'code' => 'VALIDATION_ERROR',
        ]);
});
