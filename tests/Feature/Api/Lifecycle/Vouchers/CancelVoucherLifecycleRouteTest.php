<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\VoucherLifecycleServiceContract;

it('cancels a voucher through the lifecycle route surface', function () {
    $payload = [
        'reason' => 'customer_requested',
        'notes' => 'Requested by customer.',
        'metadata' => [],
    ];

    $result = (object) [
        'voucher_id' => 99,
        'code' => 'TEST-1234',
        'status' => 'cancelled',
        'cancelled' => true,
        'reason' => 'customer_requested',
        'messages' => ['Voucher cancelled successfully.'],
    ];

    $service = Mockery::mock(VoucherLifecycleServiceContract::class);
    $service->shouldReceive('cancel')
        ->once()
        ->with('99', $payload)
        ->andReturn($result);

    $this->app->instance(VoucherLifecycleServiceContract::class, $service);

    $response = $this->postJson('/api/x/v1/vouchers/99/cancel', $payload);

    $response
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.code', 'TEST-1234')
        ->assertJsonPath('data.status', 'cancelled')
        ->assertJsonPath('data.cancelled', true);
});

it('validates required payload fields for voucher cancellation through the lifecycle route surface', function () {
    $response = $this->postJson('/api/x/v1/vouchers/99/cancel', []);

    $response
        ->assertUnprocessable()
        ->assertJson([
            'success' => false,
            'code' => 'VALIDATION_ERROR',
        ]);
});
