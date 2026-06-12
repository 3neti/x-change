<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\WithdrawalOtpApprovalServiceContract;
use LBHurtado\XChange\Services\PaynamicsWithdrawalOtpApprovalService;

it('has a bound withdrawal OTP approval service implementation', function () {
    expect(app()->bound(WithdrawalOtpApprovalServiceContract::class))->toBeTrue()
        ->and(app(WithdrawalOtpApprovalServiceContract::class))
        ->toBeInstanceOf(WithdrawalOtpApprovalServiceContract::class);
});

it('binds Paynamics withdrawal OTP approval service when configured', function () {
    config()->set('x-change.withdrawal.otp.driver', 'paynamics');

    app()->forgetInstance(WithdrawalOtpApprovalServiceContract::class);

    expect(app(WithdrawalOtpApprovalServiceContract::class))
        ->toBeInstanceOf(PaynamicsWithdrawalOtpApprovalService::class);
});
