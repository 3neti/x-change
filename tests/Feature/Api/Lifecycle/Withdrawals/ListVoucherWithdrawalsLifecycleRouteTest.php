<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\WithdrawalLifecycleServiceContract;

it('lists voucher withdrawals through the lifecycle route surface', function () {
    $result = [
        [
            'id' => 'wd-001',
            'voucher_code' => 'TEST-1234',
            'status' => 'requested',
            'amount' => 100.00,
            'currency' => 'PHP',
        ],
    ];

    $service = Mockery::mock(WithdrawalLifecycleServiceContract::class);
    $service->shouldReceive('list')
        ->once()
        ->with([])
        ->andReturn($result);

    $this->app->instance(WithdrawalLifecycleServiceContract::class, $service);

    $response = $this->getJson('/api/x/v1/withdrawals');

    $response
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.items.0.id', 'wd-001')
        ->assertJsonPath('data.items.0.voucher_code', 'TEST-1234')
        ->assertJsonPath('data.items.0.status', 'requested');
});
