<?php

use LBHurtado\Wallet\Services\SystemUserResolverService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\{System, User};

uses(RefreshDatabase::class);

test('scratch', function () {
    User::factory()->create([
        'email' => 'lester@hurtado.ph',
    ]);

    $resolvedUser = app(SystemUserResolverService::class)->resolve();
    expect($resolvedUser)->toBeInstanceOf(System::class);
});
