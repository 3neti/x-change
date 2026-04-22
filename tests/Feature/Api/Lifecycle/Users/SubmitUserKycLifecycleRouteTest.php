<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\UserLifecycleServiceContract;

it('submits user kyc through the lifecycle route surface', function () {
    $payload = [
        'transaction_id' => 'KYC-001',
        'provider' => 'hyperverge',
        'status' => 'submitted',
        'metadata' => [],
    ];

    $result = [
        'user_id' => 'usr-001',
        'status' => 'submitted',
        'transaction_id' => 'KYC-001',
        'provider' => 'hyperverge',
        'messages' => ['KYC submitted successfully.'],
    ];

    $service = Mockery::mock(UserLifecycleServiceContract::class);
    $service->shouldReceive('submitKyc')
        ->once()
        ->with('usr-001', $payload)
        ->andReturn($result);

    $this->app->instance(UserLifecycleServiceContract::class, $service);

    $response = $this->postJson('/api/x/v1/users/usr-001/kyc', $payload);

    $response
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.kyc.user_id', 'usr-001')
        ->assertJsonPath('data.kyc.transaction_id', 'KYC-001')
        ->assertJsonPath('data.kyc.status', 'submitted');
});

it('validates required payload fields for user kyc submission through the lifecycle route surface', function () {
    $response = $this->postJson('/api/x/v1/users/usr-001/kyc', []);

    $response
        ->assertUnprocessable()
        ->assertJson([
            'success' => false,
            'code' => 'VALIDATION_ERROR',
        ]);
});
