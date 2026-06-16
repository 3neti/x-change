<?php

use LBHurtado\Voucher\Models\Voucher;
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


    expect($result)->toMatchArray([
        'status' => 'approval_required',
        'voucher_code' => 'TEST-OTP',
    ])
        ->and(data_get($result, 'approval_metadata'))->toMatchArray([
            'provider' => 'paynamics',
            'authorization_type' => 'otp',
            'reference_id' => 'TEST-OTP',
            'otp_required' => true,
        ])
        ->and(data_get($result, 'approval_metadata'))->toMatchArray([
            'polling_required' => false,
            'manual_review' => false,
            'message' => 'Paynamics payout OTP is pending.',
        ]);

});
