<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\WithdrawalOtpApprovalServiceContract;

it('has a bound withdrawal OTP approval service implementation', function () {
    expect(app()->bound(WithdrawalOtpApprovalServiceContract::class))->toBeTrue()
        ->and(app(WithdrawalOtpApprovalServiceContract::class))
        ->toBeInstanceOf(WithdrawalOtpApprovalServiceContract::class);
});
