<?php

declare(strict_types=1);

use LBHurtado\EmiPaynamicsConstellation\Contracts\PendingOtpStore;
use LBHurtado\XChange\Support\Claim\ClaimApprovalPendingOtpStore;

it('implements the Paynamics pending OTP store contract', function () {
    expect(app(ClaimApprovalPendingOtpStore::class))
        ->toBeInstanceOf(PendingOtpStore::class);
});

it('stores pending OTP metadata by Paynamics request id', function () {
    $store = app(ClaimApprovalPendingOtpStore::class);

    $store->putPendingOtp([
        'request_id' => 'TEST-Z3EL-09173011987-S1',
        'bank_account_no' => '09173011987',
        'bank_id' => 'GXI',
        'reason' => 'Voucher payout TEST-Z3EL-09173011987-S1',
        'amount' => '75.00',
    ], [
        'success' => true,
        'data' => 'OTP successfully sent to 639171234567',
    ]);

    expect($store->pending('TEST-Z3EL-09173011987-S1'))->toMatchArray([
        'provider' => 'paynamics',
        'authorization_type' => 'otp',
        'reference_id' => 'TEST-Z3EL-09173011987-S1',
        'amount' => '75.00',
        'bank_account_no' => '09173011987',
        'bank_id' => 'GXI',
        'reason' => 'Voucher payout TEST-Z3EL-09173011987-S1',
        'target' => 'OTP successfully sent to 639171234567',
        'otp_required' => true,
        'polling_required' => false,
        'manual_review' => false,
        'message' => 'Paynamics payout OTP is pending.',
    ]);
});

it('returns submitted OTP by Paynamics request id', function () {
    $store = app(ClaimApprovalPendingOtpStore::class);

    $store->putSubmittedOtp('TEST-Z3EL-09173011987-S1', ' 441498 ');

    expect($store->getSubmittedOtp([
        'request_id' => 'TEST-Z3EL-09173011987-S1',
    ]))->toBe('441498');
});

it('returns null when no submitted OTP exists', function () {
    $store = app(ClaimApprovalPendingOtpStore::class);

    expect($store->getSubmittedOtp([
        'request_id' => 'TEST-MISSING',
    ]))->toBeNull();
});

it('binds the pending OTP store contract', function () {
    expect(app(PendingOtpStore::class))
        ->toBeInstanceOf(ClaimApprovalPendingOtpStore::class);
});
