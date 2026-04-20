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

it('resolves a reconciliation entry successfully', function () {
    fakePayoutProvider()
        ->willReturnPendingResult(
            transactionId: 'RECON-SUCCESS-001',
            uuid: '33333333-3333-3333-3333-333333333333',
            provider: 'fake',
        )
        ->willResolveCheckStatusAsSuccessful(
            transactionId: 'RECON-SUCCESS-001',
            uuid: '33333333-3333-3333-3333-333333333333',
            provider: 'fake',
        );

    $exitCode = Artisan::call('xchange:lifecycle:run', [
        'scenario' => 'reconciliation_resolved_success',
        '--timeout' => 1,
        '--poll' => 1,
        '--accept-pending' => true,
        '--json' => true,
    ]);

    $voucher = Voucher::query()->latest('id')->first();

    $reconciliation = DisbursementReconciliation::query()
        ->where('voucher_code', $voucher?->code)
        ->latest('id')
        ->first();

    expect($exitCode)->toBe(0);
    expect($voucher)->not->toBeNull();
    expect($voucher->redeemed_at)->not->toBeNull();

    expect($reconciliation)->not->toBeNull();
    expect($reconciliation->status)->toBe('succeeded');
    expect($reconciliation->internal_status)->toBe('recorded');
    expect((bool) $reconciliation->needs_review)->toBeFalse();
    expect($reconciliation->completed_at)->not->toBeNull();
});

it('records a failed provider outcome as pending review', function () {
    fakePayoutProvider()
        ->willReturnPendingResult(
            transactionId: 'RECON-FAILED-001',
            uuid: '44444444-4444-4444-4444-444444444444',
            provider: 'fake',
        )
        ->willResolveCheckStatusAsFailed(
            transactionId: 'RECON-FAILED-001',
            uuid: '44444444-4444-4444-4444-444444444444',
            provider: 'fake',
        );

    $exitCode = Artisan::call('xchange:lifecycle:run', [
        'scenario' => 'reconciliation_failed_pending_review',
        '--timeout' => 1,
        '--poll' => 1,
        '--accept-pending' => true,
        '--json' => true,
    ]);

    $voucher = Voucher::query()->latest('id')->first();

    $reconciliation = DisbursementReconciliation::query()
        ->where('voucher_code', $voucher?->code)
        ->latest('id')
        ->first();

    expect($exitCode)->toBe(0);
    expect($voucher)->not->toBeNull();
    expect($voucher->redeemed_at)->not->toBeNull();

    expect($reconciliation)->not->toBeNull();
    expect($reconciliation->status)->toBe('pending');
    expect($reconciliation->internal_status)->toBe('recorded');
    expect((bool) $reconciliation->needs_review)->toBeTrue();
    expect($reconciliation->completed_at)->toBeNull();
    expect($reconciliation->error_message)->not->toBeNull();
});
