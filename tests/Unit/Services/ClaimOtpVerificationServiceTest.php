<?php

declare(strict_types=1);

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\WithdrawalOtpApprovalServiceContract;
use LBHurtado\XChange\Services\WithdrawalOtpApprovalBackedClaimOtpVerificationService;

it('verifies otp through withdrawal otp verification adapter', function () {
    $voucher = new Voucher;
    $voucher->code = 'OTP-1234';

    $workflow = [
        'voucher_code' => 'OTP-1234',
        'payload' => [
            'mobile' => '639171234567',
        ],
    ];

    $otp = Mockery::mock(WithdrawalOtpApprovalServiceContract::class);

    $otp->shouldReceive('verify')
        ->once()
        ->withArgs(function (string $mobile, string $reference, string $code, array $context): bool {
            return $mobile === '639171234567'
                && $reference === 'OTP-1234'
                && $code === '123456'
                && $context['voucher_code'] === 'OTP-1234';
        })
        ->andReturnTrue();

    $service = new WithdrawalOtpApprovalBackedClaimOtpVerificationService($otp);

    $result = $service->verify(
        voucher: $voucher,
        code: '123456',
        workflow: $workflow,
    );

    expect($result)->toBeTrue();
});
