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

it('runs the signature required scenario and reports expected outcomes', function () {
    $this->artisan('xchange:lifecycle:run', [
        'scenario' => 'signature_required',
        '--timeout' => 1,
        '--poll' => 1,
        '--accept-pending' => true,
    ])
        ->expectsOutputToContain('Running scenario: signature_required')
        ->expectsOutputToContain('Attempt [missing_signature_fails]: FAILED as expected')
        ->expectsOutputToContain('Status check: expected=failed actual=failed')
        ->expectsOutputToContain('Attempt [provided_signature_succeeds]: SUCCEEDED as expected')
        ->expectsOutputToContain('Status check: expected=succeeded actual=succeeded')
        ->expectsOutputToContain('Lifecycle scenario completed.')
        ->assertExitCode(0);

    $voucher = Voucher::query()->latest('id')->first();

    expect($voucher)->not->toBeNull();
    expect($voucher->redeemed_at)->not->toBeNull();
    expect(data_get($voucher->instructions?->toArray(), 'inputs.fields'))->not->toBeEmpty();
});

it('runs the selfie required scenario and reports expected outcomes', function () {
    $this->artisan('xchange:lifecycle:run', [
        'scenario' => 'selfie_required',
        '--timeout' => 1,
        '--poll' => 1,
        '--accept-pending' => true,
    ])
        ->expectsOutputToContain('Running scenario: selfie_required')
        ->expectsOutputToContain('Attempt [missing_selfie_fails]: FAILED as expected')
        ->expectsOutputToContain('Status check: expected=failed actual=failed')
        ->expectsOutputToContain('Attempt [provided_selfie_succeeds]: SUCCEEDED as expected')
        ->expectsOutputToContain('Status check: expected=succeeded actual=succeeded')
        ->expectsOutputToContain('Lifecycle scenario completed.')
        ->assertExitCode(0);

    $voucher = Voucher::query()->latest('id')->first();

    expect($voucher)->not->toBeNull();
    expect($voucher->redeemed_at)->not->toBeNull();
    expect(data_get($voucher->instructions?->toArray(), 'inputs.fields'))->not->toBeEmpty();
});

it('runs the location required scenario and reports expected outcomes', function () {
    $this->artisan('xchange:lifecycle:run', [
        'scenario' => 'location_required',
        '--timeout' => 1,
        '--poll' => 1,
        '--accept-pending' => true,
    ])
        ->expectsOutputToContain('Running scenario: location_required')
        ->expectsOutputToContain('Attempt [missing_location_fails]: FAILED as expected')
        ->expectsOutputToContain('Status check: expected=failed actual=failed')
        ->expectsOutputToContain('Message check: matched')
        ->expectsOutputToContain('Attempt [provided_location_succeeds]: SUCCEEDED as expected')
        ->expectsOutputToContain('Status check: expected=succeeded actual=succeeded')
        ->expectsOutputToContain('Lifecycle scenario completed.')
        ->assertExitCode(0);

    $voucher = Voucher::query()->latest('id')->first();

    expect($voucher)->not->toBeNull();
    expect($voucher->redeemed_at)->not->toBeNull();
    expect(data_get($voucher->instructions?->toArray(), 'inputs.fields'))->not->toBeEmpty();
});
