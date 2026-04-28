<?php

declare(strict_types=1);

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\WithdrawalOtpApprovalServiceContract;
use LBHurtado\XChange\Services\WithdrawalOtpApprovalBackedClaimOtpChallengeService;

it('requests otp through withdrawal otp approval service', function () {
    $voucher = new Voucher;
    $voucher->code = 'OTP-1234';

    $workflow = [
        'voucher_code' => 'OTP-1234',
        'payload' => [
            'mobile' => '639171234567',
            'amount' => 1000,
        ],
    ];

    $otp = Mockery::mock(WithdrawalOtpApprovalServiceContract::class);
    $otp->shouldReceive('request')
        ->once()
        ->withArgs(function (string $mobile, string $reference, array $context): bool {
            return $mobile === '639171234567'
                && $reference === 'OTP-1234'
                && $context['amount'] === 1000
                && $context['voucher_code'] === 'OTP-1234'
                && $context['workflow']['voucher_code'] === 'OTP-1234';
        })
        ->andReturn([
            'reference' => 'OTP-REF-123',
            'provider' => 'fake',
        ]);

    $service = new WithdrawalOtpApprovalBackedClaimOtpChallengeService($otp);

    $result = $service->request($voucher, $workflow);

    expect($result)->toMatchArray([
        'driver' => 'withdrawal_otp',
        'requested' => true,
        'reference' => 'OTP-REF-123',
        'target' => '639171234567',
    ]);
});
