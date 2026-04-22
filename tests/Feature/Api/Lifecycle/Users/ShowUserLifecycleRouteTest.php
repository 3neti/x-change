<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\UserLifecycleServiceContract;

it('shows a user through the lifecycle route surface', function () {
    $result = [
        'id' => 'usr-001',
        'name' => 'Juan Dela Cruz',
        'email' => 'juan@example.com',
        'mobile' => '09171234567',
        'country' => 'PH',
        'status' => 'created',
    ];

    $service = Mockery::mock(UserLifecycleServiceContract::class);
    $service->shouldReceive('show')
        ->once()
        ->with('usr-001')
        ->andReturn($result);

    $this->app->instance(UserLifecycleServiceContract::class, $service);

    $response = $this->getJson('/api/x/v1/users/usr-001');

    $response
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.user.id', 'usr-001')
        ->assertJsonPath('data.user.name', 'Juan Dela Cruz');
});
