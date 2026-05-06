<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Tests\Fakes\User as FakeLifecycleUser;

it('runs collectible basic payment through sequential claims runner', function () {
    config()->set('x-change.lifecycle.defaults.user_model', FakeLifecycleUser::class);

    $issuer = FakeLifecycleUser::query()->create([
        'name' => 'Lifecycle Issuer',
        'email' => 'issuer@example.test',
        'password' => bcrypt('password'),
    ]);

    $issuer->setMobileChannel('09171234567');
    $issuer->save();

    fundTestUserWallet($issuer);

    $exitCode = Artisan::call('xchange:lifecycle:run', [
        'scenario' => 'collectible_basic_payment',
        '--issuer' => (string) $issuer->getKey(),
        '--wallet' => (string) $issuer->getKey(),
        '--json' => true,
    ]);

    $json = json_decode(Artisan::output(), true);

    expect($exitCode)->toBe(0)
        ->and($json['scenario'])->toBe('collectible_basic_payment')
        ->and($json['claims'])->toBeArray()
        ->and($json['attempt_summary'])->toBeArray()
        ->and($json['_bridge'] ?? null)->toBeNull();

    expect(Voucher::query()->latest('id')->first())->not->toBeNull();
});
