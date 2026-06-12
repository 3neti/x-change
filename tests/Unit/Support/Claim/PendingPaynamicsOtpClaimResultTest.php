<?php

declare(strict_types=1);

use LBHurtado\EmiPaynamicsConstellation\Exceptions\PendingConstellationOtpException;
use LBHurtado\XChange\Support\Claim\PendingPaynamicsOtpClaimResult;

it('converts pending Paynamics OTP exception to claim approval result', function () {
    $voucher = issueVoucher();

    $exception = PendingConstellationOtpException::fromPayload([
        'request_id' => 'TEST-Z3EL-09173011987-S1',
        'bank_account_no' => '09173011987',
        'bank_id' => 'GXI',
        'reason' => 'Voucher payout TEST-Z3EL-09173011987-S1',
        'amount' => '75.00',
    ], [
        'success' => true,
        'data' => 'OTP successfully sent to 639171234567',
    ]);

    $result = app(PendingPaynamicsOtpClaimResult::class)
        ->fromException($voucher, $exception);

    expect($result)->toMatchArray([
        'status' => 'approval_required',
        'voucher_code' => $voucher->code,
        'reference_id' => 'TEST-Z3EL-09173011987-S1',
        'provider' => 'paynamics',
        'messages' => [
            'Payout OTP approval required.',
        ],
        'approval_metadata' => [
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
        ],
    ]);
});

