<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\WithdrawalOtpApprovalServiceContract;
use LBHurtado\XChange\Services\PaynamicsWithdrawalOtpApprovalService;
use LBHurtado\XChange\Support\Claim\ClaimApprovalPendingOtpStore;

it('implements the withdrawal OTP approval service contract', function () {
    expect(app(PaynamicsWithdrawalOtpApprovalService::class))
        ->toBeInstanceOf(WithdrawalOtpApprovalServiceContract::class);
});

it('stores submitted Paynamics OTP by reference', function () {
    $service = app(PaynamicsWithdrawalOtpApprovalService::class);

    $verified = $service->verify(
        mobile: '639171234567',
        reference: 'TEST-Z3EL-09173011987-S1',
        code: ' 441498 ',
        context: [
            'voucher_code' => 'TEST-Z3EL',
        ],
    );

    expect($verified)->toBeTrue()
        ->and(app(ClaimApprovalPendingOtpStore::class)->getSubmittedOtp([
            'request_id' => 'TEST-Z3EL-09173011987-S1',
        ]))->toBe('441498');
});

it('does not store blank Paynamics OTP submissions', function () {
    $service = app(PaynamicsWithdrawalOtpApprovalService::class);

    expect($service->verify(
        mobile: '639171234567',
        reference: 'TEST-Z3EL-09173011987-S1',
        code: '   ',
        context: [],
    ))->toBeFalse()
        ->and(app(ClaimApprovalPendingOtpStore::class)->getSubmittedOtp([
            'request_id' => 'TEST-Z3EL-09173011987-S1',
        ]))->toBeNull();
});

it('returns pending Paynamics OTP metadata when available', function () {
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

    $result = app(PaynamicsWithdrawalOtpApprovalService::class)->request(
        mobile: '639171234567',
        reference: 'TEST-Z3EL-09173011987-S1',
        context: [
            'voucher_code' => 'TEST-Z3EL',
            'amount' => 75,
        ],
    );

    expect($result)->toMatchArray([
        'provider' => 'paynamics',
        'reference' => 'TEST-Z3EL-09173011987-S1',
        'requested' => true,
        'target' => 'OTP successfully sent to 639171234567',
        'message' => 'Paynamics payout OTP is pending.',
    ])
        ->and(data_get($result, 'approval_metadata'))->toMatchArray([
            'provider' => 'paynamics',
            'authorization_type' => 'otp',
            'reference_id' => 'TEST-Z3EL-09173011987-S1',
            'otp_required' => true,
        ]);
});
