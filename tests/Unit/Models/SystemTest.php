<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use App\Models\{User, System};

uses(RefreshDatabase::class);

beforeEach(function () {
    Config::set('account.system_user.identifier_column', 'email');
    Config::set('account.system_user.identifier', 'system@example.com');
});

test('system child from create', function () {
    $user = User::factory()->create([
        'type' => 'system',
    ]);

    $system = User::find($user->id);
    expect($system)->toBeInstanceOf(System::class);
});

test('system child from update', function () {
    $user = User::factory()->create();

    $system = User::find($user->id);
    expect($system)->not()->toBeInstanceOf(System::class);

    $user->type = 'system';
    $user->save();

    $system = User::find($user->id);
    expect($system)->toBeInstanceOf(System::class);
});
