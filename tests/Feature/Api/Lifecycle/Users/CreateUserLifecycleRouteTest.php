<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\UserLifecycleServiceContract;

it('creates a user through the lifecycle route surface', function () {
    $payload = [
        'name' => 'Juan Dela Cruz',
        'email' => 'juan@example.com',
        'mobile' => '09171234567',
        'country' => 'PH',
        'metadata' => [],
    ];

    $result = [
        'id' => 'usr-001',
        'name' => 'Juan Dela Cruz',
        'email' => 'juan@example.com',
        'mobile' => '09171234567',
        'country' => 'PH',
        'status' => 'created',
    ];

    $service = Mockery::mock(UserLifecycleServiceContract::class);
    $service->shouldReceive('create')
        ->once()
        ->with($payload)
        ->andReturn($result);

    $this->app->instance(UserLifecycleServiceContract::class, $service);

    $response = $this->postJson('/api/x/v1/users', $payload);

    $response
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.user.id', 'usr-001')
        ->assertJsonPath('data.user.name', 'Juan Dela Cruz')
        ->assertJsonPath('data.user.status', 'created');
});

it('validates required payload fields for user creation through the lifecycle route surface', function () {
    $response = $this->postJson('/api/x/v1/users', []);

    $response
        ->assertUnprocessable()
        ->assertJson([
            'success' => false,
            'code' => 'VALIDATION_ERROR',
        ]);
});
