<?php

declare(strict_types=1);

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\ClaimOtpVerificationContract;
use LBHurtado\XChange\Services\WithdrawalOtpApprovalBackedClaimOtpVerificationService;

it('implements the claim OTP verification contract', function () {
    expect(app(WithdrawalOtpApprovalBackedClaimOtpVerificationService::class))
        ->toBeInstanceOf(ClaimOtpVerificationContract::class);
});

it('verifies a withdrawal-backed claim approval OTP through its current backing service', function () {
    $voucher = issueVoucher();

    $service = app(WithdrawalOtpApprovalBackedClaimOtpVerificationService::class);

    $result = $service->verify($voucher, '441498', [
        'reference_id' => 'PAYNAMICS-AUTH-123',
        'provider' => 'paynamics',
        'amount' => 75,
        'currency' => 'PHP',
    ]);

    expect($result)->toBeBool();
});
