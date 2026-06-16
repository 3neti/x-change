<?php

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Data\Claims\ApprovalStatusData;
use LBHurtado\XChange\Support\Claim\ClaimApprovalPendingOtpStore;
use LBHurtado\XChange\Support\Claim\DefaultClaimApprovalStatusResolver;

it('resolves pending Paynamics OTP approval status from pending OTP store', function () {
    $voucher = new Voucher;
    $voucher->code = 'TEST-OTP';

    app(ClaimApprovalPendingOtpStore::class)->putPendingOtp([
        'request_id' => 'TEST-OTP',
        'bank_account_no' => '09173011987',
        'bank_id' => 'GXI',
        'reason' => 'Voucher payout TEST-OTP',
        'amount' => '75.00',
    ], [
        'success' => true,
        'data' => 'OTP successfully sent to 639171234567',
    ]);

    $result = app(DefaultClaimApprovalStatusResolver::class)->resolve($voucher);

    expect($result)->toBeInstanceOf(ApprovalStatusData::class);

    $payload = $result->toCompiledClaimResult();

    expect($payload)->toMatchArray([
        'status' => 'approval_required',
        'voucher_code' => 'TEST-OTP',
    ])
        ->and(data_get($payload, 'approval_metadata'))->toMatchArray([
            'provider' => 'paynamics',
            'authorization_type' => 'otp',
            'reference_id' => 'TEST-OTP',
            'otp_required' => true,
        ])
        ->and(data_get($payload, 'approval_metadata'))->toMatchArray([
            'polling_required' => false,
            'manual_review' => false,
            'message' => 'Paynamics payout OTP is pending.',
        ]);

});

it('resolves pending Paynamics OTP approval status from voucher disbursement metadata reference', function () {
    $voucher = new Voucher;
    $voucher->code = 'ZZ5M';
    $voucher->metadata = [
        'disbursement' => [
            'transaction_id' => 'ZZ5M-09173011987',
            'recipient_identifier' => '09173011987',
        ],
    ];

    app(ClaimApprovalPendingOtpStore::class)->putPendingOtp([
        'request_id' => 'ZZ5M-09173011987',
        'bank_account_no' => '09173011987',
        'bank_id' => 'GXCHPHM2XXX',
        'reason' => 'Voucher payout ZZ5M-09173011987',
        'amount' => '50.00',
    ], [
        'success' => true,
        'data' => 'OTP successfully sent',
    ]);

    $result = app(DefaultClaimApprovalStatusResolver::class)->resolve($voucher);

    expect($result)->toBeInstanceOf(ApprovalStatusData::class)
        ->and($result->reference_id)->toBe('ZZ5M-09173011987')
        ->and($result->otp_required)->toBeTrue();
});
