<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\UserLifecycleServiceContract;

it('shows user kyc through the lifecycle route surface', function () {
    $result = [
        'user_id' => 'usr-001',
        'status' => 'approved',
        'transaction_id' => 'KYC-001',
        'provider' => 'hyperverge',
        'messages' => ['KYC approved.'],
    ];

    $service = Mockery::mock(UserLifecycleServiceContract::class);
    $service->shouldReceive('showKyc')
        ->once()
        ->with('usr-001')
        ->andReturn($result);

    $this->app->instance(UserLifecycleServiceContract::class, $service);

    $response = $this->getJson('/api/x/v1/users/usr-001/kyc');

    $response
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.kyc.user_id', 'usr-001')
        ->assertJsonPath('data.kyc.status', 'approved')
        ->assertJsonPath('data.kyc.transaction_id', 'KYC-001');
});
