<?php

use LBHurtado\PaymentGateway\Services\WalletProvisioningService;
use LBHurtado\PaymentGateway\Tests\Models\User;
use Illuminate\Support\Facades\App;

it('provisions default wallets on user creation', function () {
    // Fake the WalletProvisioningService and bind it to the container
    $mock = Mockery::mock(WalletProvisioningService::class);
    $mock->shouldReceive('createDefaultWalletsForUser')->once();
    App::instance(WalletProvisioningService::class, $mock);

    // Create a user (this should trigger the booted logic)
    User::factory()->create();
});
