<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Models\DisbursementReconciliation;

beforeEach(function () {
    config()->set('x-change.lifecycle.defaults.user_model', \LBHurtado\XChange\Tests\Fakes\User::class);
    config()->set('x-change.lifecycle.defaults.system_user_email', 'system@example.test');
    config()->set('x-change.lifecycle.defaults.test_user_email', 'lester@hurtado.ph');
    config()->set('x-change.lifecycle.defaults.test_user_mobile', '09173011987');
    config()->set('queue.default', 'sync');

    fakePayoutProvider();

    Artisan::call('xchange:lifecycle:prepare', [
        '--seed' => true,
    ]);

    expect(Schema::hasTable('inputs'))->toBeTrue();
    expect(Schema::hasTable('contacts'))->toBeTrue();
});

it('records a reconciliation entry that requires review', function () {
    fakePayoutProvider()->willReturnFailedResult(
        transactionId: 'REVIEW-REQ-001',
        uuid: '11111111-1111-1111-1111-111111111111',
        provider: 'fake',
    );

    $exitCode = Artisan::call('xchange:lifecycle:run', [
        'scenario' => 'reconciliation_review_required',
        '--timeout' => 1,
        '--poll' => 1,
        '--accept-pending' => true,
        '--json' => true,
    ]);

    $output = Artisan::output();
    $payload = $output !== '' ? json_decode($output, true) : null;

    $voucher = Voucher::query()->latest('id')->first();
    $reconciliation = DisbursementReconciliation::query()
        ->where('voucher_code', $voucher?->code)
        ->latest('id')
        ->first();

    expect($exitCode)->toBe(0);
    expect($voucher)->not->toBeNull();
    expect($voucher->redeemed_at)->not->toBeNull();

    expect($reconciliation)->not->toBeNull();
    expect((bool) $reconciliation->needs_review)->toBeTrue();
    expect($reconciliation->review_reason)->not->toBeNull();
    expect($reconciliation->status)->not->toBeNull();
    expect($reconciliation->internal_status)->not->toBeNull();

    if (is_array($payload)) {
        expect($payload['scenario'])->toBe('reconciliation_review_required');
    }
});

it('records provider failure metadata for reconciliation follow-up', function () {
    fakePayoutProvider()->willReturnFailedResult(
        transactionId: 'PROVIDER-FAIL-001',
        uuid: '22222222-2222-2222-2222-222222222222',
        provider: 'fake',
    );

    $exitCode = Artisan::call('xchange:lifecycle:run', [
        'scenario' => 'reconciliation_provider_failed_recorded',
        '--timeout' => 1,
        '--poll' => 1,
        '--accept-pending' => true,
        '--json' => true,
    ]);

    $output = Artisan::output();
    $payload = $output !== '' ? json_decode($output, true) : null;

    $voucher = Voucher::query()->latest('id')->first();
    $reconciliation = DisbursementReconciliation::query()
        ->where('voucher_code', $voucher?->code)
        ->latest('id')
        ->first();

    expect($exitCode)->toBe(0);
    expect($voucher)->not->toBeNull();
    expect($voucher->redeemed_at)->not->toBeNull();

    expect($reconciliation)->not->toBeNull();
    expect($reconciliation->status)->not->toBeNull();
    expect($reconciliation->internal_status)->not->toBeNull();
    expect($reconciliation->error_message)->not->toBeNull();
    expect($reconciliation->raw_response)->not->toBeNull();
    expect($reconciliation->provider_reference)->not->toBeNull();

    if (is_array($payload)) {
        expect($payload['scenario'])->toBe('reconciliation_provider_failed_recorded');
    }
});
