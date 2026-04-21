<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\VoucherLifecycleServiceContract;

it('lists vouchers through the lifecycle route surface', function () {
    $result = [
        [
            'id' => 1,
            'voucher_id' => 99,
            'code' => 'TEST-1234',
            'amount' => 100.00,
            'currency' => 'PHP',
            'status' => 'issued',
            'issuer_id' => 1,
        ],
    ];

    $service = Mockery::mock(VoucherLifecycleServiceContract::class);
    $service->shouldReceive('list')
        ->once()
        ->with([])
        ->andReturn($result);

    $this->app->instance(VoucherLifecycleServiceContract::class, $service);

    $response = $this->getJson('/api/x/v1/vouchers');

    $response
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.items.0.code', 'TEST-1234')
        ->assertJsonPath('data.items.0.status', 'issued');
});
