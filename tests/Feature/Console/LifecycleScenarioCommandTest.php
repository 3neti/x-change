<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use LBHurtado\Voucher\Models\Voucher;

beforeEach(function () {
    config()->set('x-change.lifecycle.defaults.user_model', \LBHurtado\XChange\Tests\Fakes\User::class);
    config()->set('x-change.lifecycle.defaults.system_user_email', 'system@example.test');
    config()->set('x-change.lifecycle.defaults.test_user_email', 'lester@hurtado.ph');
    config()->set('x-change.lifecycle.defaults.test_user_mobile', '09173011987');
    config()->set('queue.default', 'sync');

    Artisan::call('xchange:lifecycle:prepare', [
        '--seed' => true,
    ]);
});

it('runs a legacy lifecycle scenario through issuance successfully', function () {
    expect(Schema::hasTable('inputs'))->toBeTrue();
    expect(Schema::hasTable('contacts'))->toBeTrue();

    $exitCode = Artisan::call('xchange:lifecycle:run', [
        'scenario' => 'basic_cash',
        '--no-claim' => true,
    ]);

    $voucher = Voucher::query()->latest('id')->first();

    expect($exitCode)->toBe(0);
    expect($voucher)->not->toBeNull();
    expect($voucher->code)->toStartWith('TEST-');
    expect($voucher->redeemed_at)->toBeNull();
});

it('runs a multi-attempt contract scenario and reports expected outcomes', function () {
    $this->artisan('xchange:lifecycle:run', [
        'scenario' => 'secret_required',
        '--timeout' => 1,
        '--poll' => 1,
        '--accept-pending' => true,
    ])
        ->expectsOutputToContain('Running scenario: secret_required')
        ->expectsOutputToContain('Attempt [wrong_secret_fails]: FAILED as expected')
        ->expectsOutputToContain('Status check: expected=failed actual=failed')
        ->expectsOutputToContain('Message check: matched')
        ->expectsOutputToContain('Attempt [correct_secret_succeeds]: SUCCEEDED as expected')
        ->expectsOutputToContain('Status check: expected=succeeded actual=succeeded')
        ->expectsOutputToContain('Lifecycle scenario completed.')
        ->assertExitCode(0);

    $voucher = Voucher::query()->latest('id')->first();

    expect($voucher)->not->toBeNull();
    expect($voucher->redeemed_at)->not->toBeNull();
    expect(data_get($voucher->instructions?->toArray(), 'cash.validation.secret'))->toBe('ABC123');
});

it('runs a blocked time-window scenario without redeeming the voucher', function () {
    $this->artisan('xchange:lifecycle:run', [
        'scenario' => 'expired_voucher',
    ])
        ->expectsOutputToContain('Running scenario: expired_voucher')
        ->expectsOutputToContain('Attempt [after_expiry_fails]: FAILED as expected')
        ->expectsOutputToContain('Status check: expected=failed actual=failed')
        ->expectsOutputToContain('Lifecycle scenario completed.')
        ->assertExitCode(0);

    $voucher = Voucher::query()->latest('id')->first();

    expect($voucher)->not->toBeNull();
    expect($voucher->redeemed_at)->toBeNull();
    expect($voucher->expires_at)->not->toBeNull();
});
