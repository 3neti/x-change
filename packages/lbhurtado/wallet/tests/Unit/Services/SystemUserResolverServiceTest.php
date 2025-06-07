<?php

use LBHurtado\Wallet\Services\SystemUserResolverService;
use LBHurtado\Wallet\Tests\Models\User;
use Illuminate\Support\Facades\Config;

it('resolves the system user based on config/account.php', function () {
    // Arrange: set config values
    Config::set('account.system_user.identifier', 'apple@hurtado.ph');
    Config::set('account.system_user.identifier_column', 'email');
    Config::set('account.system_user.model', User::class);

    // Seed or ensure a user exists
    $user = User::factory()->create([
        'email' => 'apple@hurtado.ph',
    ]);

    // Act
    $resolvedUser = app(SystemUserResolverService::class)->resolve();

    // Assert
    expect($resolvedUser->is($user))->toBeTrue();
});
