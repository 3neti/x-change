<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\VoucherLifecycleServiceContract;

it('shows voucher status through the lifecycle route surface', function () {
    $result = (object) [
        'voucher_id' => 99,
        'code' => 'TEST-1234',
        'status' => 'issued',
        'claimed' => false,
        'fully_claimed' => false,
        'remaining_balance' => 100.00,
        'currency' => 'PHP',
    ];

    $service = Mockery::mock(VoucherLifecycleServiceContract::class);
    $service->shouldReceive('status')
        ->once()
        ->with('99')
        ->andReturn($result);

    $this->app->instance(VoucherLifecycleServiceContract::class, $service);

    $response = $this->getJson('/api/x/v1/vouchers/99/status');

    $response
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.code', 'TEST-1234')
        ->assertJsonPath('data.status', 'issued');
});
