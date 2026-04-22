<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\WithdrawalLifecycleServiceContract;

it('creates a voucher withdrawal through the lifecycle route surface', function () {
    $payload = [
        'voucher_code' => 'TEST-1234',
        'amount' => 100.00,
        'currency' => 'PHP',
        'bank_account' => [
            'bank_code' => 'GXCHPHM2XXX',
            'account_number' => '09171234567',
        ],
        'notes' => 'Customer withdrawal request.',
        'metadata' => [],
    ];

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
    $service->shouldReceive('create')
        ->once()
        ->with($payload)
        ->andReturn($result);

    $this->app->instance(WithdrawalLifecycleServiceContract::class, $service);

    $response = $this->postJson('/api/x/v1/withdrawals', $payload);

    $response
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.withdrawal.id', 'wd-001')
        ->assertJsonPath('data.withdrawal.voucher_code', 'TEST-1234')
        ->assertJsonPath('data.withdrawal.status', 'requested');
});

it('validates required payload fields for voucher withdrawal through the lifecycle route surface', function () {
    $response = $this->postJson('/api/x/v1/withdrawals', []);

    $response
        ->assertUnprocessable()
        ->assertJson([
            'success' => false,
            'code' => 'VALIDATION_ERROR',
        ]);
});
