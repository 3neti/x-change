<?php

use LBHurtado\XChange\Data\WithdrawalPipelineContextData;
use LBHurtado\XChange\Services\WithdrawalPipelineSteps\AuthorizeWithdrawalOtpStep;

it('enables all non-conditional built-in withdrawal pipeline steps by default', function () {
    $voucher = issueVoucher();

    $context = new WithdrawalPipelineContextData(
        voucher: $voucher,
        payload: [],
    );

    $conditionalSteps = [
        AuthorizeWithdrawalOtpStep::class,
    ];

    foreach (config('x-change.withdrawal.pipeline.steps') as $step) {
        if (in_array($step, $conditionalSteps, true)) {
            continue;
        }

        expect($step::shouldRun($context))->toBeTrue();
    }
});

it('runs otp authorization step only when otp is required', function () {
    $voucher = issueVoucher();

    $context = new WithdrawalPipelineContextData(
        voucher: $voucher,
        payload: [],
    );

    expect(AuthorizeWithdrawalOtpStep::shouldRun($context))->toBeFalse();

    $contextWithOtp = new WithdrawalPipelineContextData(
        voucher: $voucher,
        payload: [
            'authorization' => [
                'otp_required' => true,
            ],
        ],
    );

    expect(AuthorizeWithdrawalOtpStep::shouldRun($contextWithOtp))->toBeTrue();
});
