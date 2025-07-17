<?php

use Illuminate\Support\Facades\Hash;
use LBHurtado\Cash\Models\Cash;
use LBHurtado\OmniChannel\Notifications\AdhocNotification;
use LBHurtado\Wallet\Services\SystemUserResolverService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\PaymentGateway\Support\BankRegistry;
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

test('bank codes', function () {
    dd(app(BankRegistry::class)->toCollection());
})->skip();

test('hash works', function () {
    $amount = 100;
    $currency = 'PHP';
    $secret = 'password';
    $cash = Cash::create([
        'amount'   => $amount,
        'currency' => $currency,
        'meta'     => ['notes' => 'change this'],
        'secret'   => $secret,
    ]);

   $hashed_secret = $cash->secret;

    dump('Hash:', $cash->secret);
    dump('Driver:', config('hashing.driver'));
   expect(strlen($hashed_secret))->toBe(60);
   expect(Hash::check($secret, $hashed_secret))->toBeTrue();
});
