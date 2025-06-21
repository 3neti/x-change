<?php

use LBHurtado\OmniChannel\Notifications\AdhocNotification;
use LBHurtado\Wallet\Services\SystemUserResolverService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\{System, User};

uses(RefreshDatabase::class);

test('scratch', function () {
    User::factory()->create([
        'email' => 'admin@disburse.cash',
    ]);

    $resolvedUser = app(SystemUserResolverService::class)->resolve();
    expect($resolvedUser)->toBeInstanceOf(System::class);
});

test('send notification', function () {
    $user = User::factory()->create();
    $user->mobile = '09467438575';
    $user->save();
    $user->notify(new AdhocNotification('Who in the world is Leslie Chiong?'));
})->skip();
