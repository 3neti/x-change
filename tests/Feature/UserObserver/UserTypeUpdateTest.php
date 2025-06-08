<?php

use LBHurtado\Wallet\Services\SystemUserResolverService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use App\Models\User;

uses(RefreshDatabase::class);

beforeEach(function () {
    Config::set('account.system_user.identifier_column', 'email');
    Config::set('account.system_user.identifier', 'system@example.com');
});

test('user type is set to system when created with system identifier', function () {
    $user = User::factory()->create([
        'email' => 'system@example.com',
    ]);

    expect($user->type)->toBe('system');
    $resolvedSystemUser = app(SystemUserResolverService::class)->resolve();
    expect($resolvedSystemUser->is($user))->toBeTrue();
});

test('user type is set to system when updated to system identifier', function () {
    $user = User::factory()->create([
        'email' => 'user@example.com',
        'type' => null,
    ]);

    $user->update(['email' => 'system@example.com']);
    $user->refresh();

    expect($user->type)->toBe('system');
    $resolvedSystemUser = app(SystemUserResolverService::class)->resolve();
    expect($resolvedSystemUser->is($user))->toBeTrue();
});

test('user type is nullified when updated away from system identifier', function () {
    $user = User::factory()->create([
        'email' => 'system@example.com',
        'type' => 'system',
    ]);

    $user->update(['email' => 'user@example.com']);
    $user->refresh();

    expect($user->type)->toBeNull();
});
