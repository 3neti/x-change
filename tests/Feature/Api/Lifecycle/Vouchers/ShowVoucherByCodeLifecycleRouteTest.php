<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\VoucherLifecycleServiceContract;

it('shows a voucher by code through the lifecycle route surface', function () {
    $result = (object) [
        'id' => 1,
        'voucher_id' => 99,
        'code' => 'TEST-1234',
        'amount' => 100.00,
        'currency' => 'PHP',
        'status' => 'issued',
        'issuer_id' => 1,
        'claimed' => false,
        'fully_claimed' => false,
    ];

    $service = Mockery::mock(VoucherLifecycleServiceContract::class);
    $service->shouldReceive('showByCode')
        ->once()
        ->with('TEST-1234')
        ->andReturn($result);

    $this->app->instance(VoucherLifecycleServiceContract::class, $service);

    $response = $this->getJson('/api/x/v1/vouchers/code/TEST-1234');

    $response
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.voucher.code', 'TEST-1234');
});
