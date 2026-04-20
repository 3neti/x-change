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

    expect(Schema::hasTable('inputs'))->toBeTrue();
    expect(Schema::hasTable('contacts'))->toBeTrue();
});

it('runs the mobile locked contract scenario and reports expected outcomes', function () {
    $this->artisan('xchange:lifecycle:run', [
        'scenario' => 'mobile_locked',
        '--timeout' => 1,
        '--poll' => 1,
        '--accept-pending' => true,
    ])
        ->expectsOutputToContain('Running scenario: mobile_locked')
        ->expectsOutputToContain('Attempt [wrong_mobile_fails]: FAILED as expected')
        ->expectsOutputToContain('Status check: expected=failed actual=failed')
        ->expectsOutputToContain('Message check: matched')
        ->expectsOutputToContain('Attempt [correct_mobile_succeeds]: SUCCEEDED as expected')
        ->expectsOutputToContain('Status check: expected=succeeded actual=succeeded')
        ->expectsOutputToContain('Lifecycle scenario completed.')
        ->assertExitCode(0);

    $voucher = Voucher::query()->latest('id')->first();

    expect($voucher)->not->toBeNull();
    expect($voucher->redeemed_at)->not->toBeNull();
    expect(data_get($voucher->instructions?->toArray(), 'cash.validation.mobile'))->toBe('639171234567');
});

it('runs the bio inputs contract scenario and reports expected outcomes', function () {
    $this->artisan('xchange:lifecycle:run', [
        'scenario' => 'bio_inputs_required',
        '--timeout' => 1,
        '--poll' => 1,
        '--accept-pending' => true,
    ])
        ->expectsOutputToContain('Running scenario: bio_inputs_required')
        ->expectsOutputToContain('Attempt [missing_fields_fail]: FAILED as expected')
        ->expectsOutputToContain('Status check: expected=failed actual=failed')
        ->expectsOutputToContain('Message check: matched')
        ->expectsOutputToContain('Attempt [complete_fields_succeed]: SUCCEEDED as expected')
        ->expectsOutputToContain('Status check: expected=succeeded actual=succeeded')
        ->expectsOutputToContain('Lifecycle scenario completed.')
        ->assertExitCode(0);

    $voucher = Voucher::query()->latest('id')->first();

    expect($voucher)->not->toBeNull();
    expect($voucher->redeemed_at)->not->toBeNull();
    expect(data_get($voucher->instructions?->toArray(), 'inputs.fields'))->not->toBeEmpty();
});

it('runs the otp contract scenario and reports expected outcomes', function () {
    $this->artisan('xchange:lifecycle:run', [
        'scenario' => 'otp_required',
        '--timeout' => 1,
        '--poll' => 1,
        '--accept-pending' => true,
    ])
        ->expectsOutputToContain('Running scenario: otp_required')
        ->expectsOutputToContain('Attempt [missing_otp_fails]: FAILED as expected')
        ->expectsOutputToContain('Attempt [unverified_otp_fails]: FAILED as expected')
        ->expectsOutputToContain('Attempt [verified_otp_succeeds]: SUCCEEDED as expected')
        ->expectsOutputToContain('Status check: expected=failed actual=failed')
        ->expectsOutputToContain('Status check: expected=succeeded actual=succeeded')
        ->expectsOutputToContain('Lifecycle scenario completed.')
        ->assertExitCode(0);

    $voucher = Voucher::query()->latest('id')->first();

    expect($voucher)->not->toBeNull();
    expect(data_get($voucher->instructions?->toArray(), 'validation.otp.required'))->toBeTrue();
});

it('runs the location radius contract scenario and reports expected outcomes', function () {
    $this->artisan('xchange:lifecycle:run', [
        'scenario' => 'location_radius',
        '--timeout' => 1,
        '--poll' => 1,
        '--accept-pending' => true,
    ])
        ->expectsOutputToContain('Running scenario: location_radius')
        ->expectsOutputToContain('Attempt [outside_radius_fails]: FAILED as expected')
        ->expectsOutputToContain('Attempt [inside_radius_succeeds]: SUCCEEDED as expected')
        ->expectsOutputToContain('Status check: expected=failed actual=failed')
        ->expectsOutputToContain('Status check: expected=succeeded actual=succeeded')
        ->expectsOutputToContain('Lifecycle scenario completed.')
        ->assertExitCode(0);

    $voucher = Voucher::query()->latest('id')->first();

    expect($voucher)->not->toBeNull();
    expect($voucher->redeemed_at)->not->toBeNull();
    expect(data_get($voucher->instructions?->toArray(), 'validation.location.required'))->toBeTrue();
});
