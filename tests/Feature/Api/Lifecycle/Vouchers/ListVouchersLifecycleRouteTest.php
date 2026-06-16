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
            'approval' => [
                'required' => true,
                'type' => 'otp',
                'provider' => 'paynamics',
                'reference_id' => 'TEST-1234-09173011987',
                'message' => 'Paynamics payout OTP is pending.',
                'action_url' => '/x/pay-codes/TEST-1234/approval',
            ],
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
        ->assertJsonPath('data.items.0.status', 'issued')
        ->assertJsonPath('data.items.0.approval.required', true)
        ->assertJsonPath('data.items.0.approval.type', 'otp')
        ->assertJsonPath('data.items.0.approval.provider', 'paynamics')
        ->assertJsonPath('data.items.0.approval.reference_id', 'TEST-1234-09173011987');
});
