<?php

declare(strict_types=1);

use LBHurtado\XChange\Actions\Claim\SubmitClaimApprovalOtp;

it('returns received approval OTP result for voucher', function () {
    $voucher = issueVoucher();

    $result = app(SubmitClaimApprovalOtp::class)->handle($voucher, [
        'otp' => '123456',
        'reference_id' => 'AUTH-123',
        'provider' => 'payanamics',
    ]);

    expect($result)->toBe([
        'status' => 'received',
        'voucher_code' => $voucher->code,
        'reference_id' => 'AUTH-123',
        'provider' => 'payanamics',
    ]);
});

it('defaults optional approval OTP metadata to null', function () {
    $voucher = issueVoucher();

    $result = app(SubmitClaimApprovalOtp::class)->handle($voucher, [
        'otp' => '123456',
    ]);

    expect($result)->toBe([
        'status' => 'received',
        'voucher_code' => $voucher->code,
        'reference_id' => null,
        'provider' => null,
    ]);
});
