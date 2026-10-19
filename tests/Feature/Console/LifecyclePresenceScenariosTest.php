<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Models\VoucherClaimEvidence;
use LBHurtado\XChange\Services\Commercial\ProvisionCommercialBaselines;
use LBHurtado\XChange\Tests\Fakes\User;

beforeEach(function () {
    config()->set('x-change.lifecycle.defaults.user_model', User::class);
    config()->set('x-change.lifecycle.defaults.system_user_email', 'system@example.test');
    config()->set('x-change.lifecycle.defaults.test_user_email', 'lester@hurtado.ph');
    config()->set('x-change.lifecycle.defaults.test_user_mobile', '09173011987');
    config()->set('queue.default', 'sync');
    Storage::fake((string) config('x-change.claim.evidence.disk', 'local'));

    app(ProvisionCommercialBaselines::class)->provision('lifecycle-presence-fixture:v1');

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
    $evidence = VoucherClaimEvidence::query()->where('voucher_id', $voucher->getKey())->where('requirement_key', 'signature')->sole();
    expect($evidence->mime_type)->toBe('image/png')
        ->and($evidence->size)->toBeGreaterThan(0);
    Storage::disk($evidence->artifact_disk)->assertExists($evidence->artifact_path);

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
    $evidence = VoucherClaimEvidence::query()->where('voucher_id', $voucher->getKey())->where('requirement_key', 'selfie')->sole();
    expect($evidence->mime_type)->toBe('image/png')
        ->and($evidence->size)->toBeGreaterThan(0);
    Storage::disk($evidence->artifact_disk)->assertExists($evidence->artifact_path);

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
