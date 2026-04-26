<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\WithdrawalOtpApprovalServiceContract;
use LBHurtado\XChange\Services\NullWithdrawalOtpApprovalService;
use LBHurtado\XChange\Services\TxtcmdrWithdrawalOtpApprovalService;

it('resolves null withdrawal otp driver by default', function () {
    config()->set('x-change.withdrawal.otp.driver', 'null');

    expect(app(WithdrawalOtpApprovalServiceContract::class))
        ->toBeInstanceOf(NullWithdrawalOtpApprovalService::class);
});

it('resolves txtcmdr withdrawal otp driver', function () {
    config()->set('x-change.withdrawal.otp.driver', 'txtcmdr');

    app()->forgetInstance(WithdrawalOtpApprovalServiceContract::class);

    expect(app(WithdrawalOtpApprovalServiceContract::class))
        ->toBeInstanceOf(TxtcmdrWithdrawalOtpApprovalService::class);
});

it('fails for unsupported withdrawal otp driver', function () {
    config()->set('x-change.withdrawal.otp.driver', 'bogus');

    app()->forgetInstance(WithdrawalOtpApprovalServiceContract::class);

    app(WithdrawalOtpApprovalServiceContract::class);
})->throws(InvalidArgumentException::class, 'Unsupported withdrawal OTP driver: bogus');
