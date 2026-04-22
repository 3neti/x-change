<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\WithdrawalLifecycleServiceContract;

it('shows a voucher withdrawal through the lifecycle route surface', function () {
    $result = [
        'id' => 'wd-001',
        'voucher_code' => 'TEST-1234',
        'status' => 'requested',
        'amount' => 100.00,
        'currency' => 'PHP',
        'bank_code' => 'GXCHPHM2XXX',
        'account_number' => '09171234567',
        'messages' => ['Withdrawal requested successfully.'],
    ];

    $service = Mockery::mock(WithdrawalLifecycleServiceContract::class);
    $service->shouldReceive('show')
        ->once()
        ->with('wd-001')
        ->andReturn($result);

    $this->app->instance(WithdrawalLifecycleServiceContract::class, $service);

    $response = $this->getJson('/api/x/v1/withdrawals/wd-001');

    $response
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.withdrawal.id', 'wd-001')
        ->assertJsonPath('data.withdrawal.voucher_code', 'TEST-1234')
        ->assertJsonPath('data.withdrawal.status', 'requested');
});
