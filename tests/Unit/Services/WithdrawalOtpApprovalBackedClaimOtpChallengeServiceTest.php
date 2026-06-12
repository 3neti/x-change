<?php

declare(strict_types=1);

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\ClaimOtpChallengeContract;
use LBHurtado\XChange\Services\WithdrawalOtpApprovalBackedClaimOtpChallengeService;

it('implements the claim OTP challenge contract', function () {
    expect(app(WithdrawalOtpApprovalBackedClaimOtpChallengeService::class))
        ->toBeInstanceOf(ClaimOtpChallengeContract::class);
});

it('requests a withdrawal-backed claim approval OTP challenge', function () {
    $voucher = issueVoucher();

    $service = app(WithdrawalOtpApprovalBackedClaimOtpChallengeService::class);

    $result = $service->request($voucher, [
        'payload' => [
            'mobile' => '639171234567',
            'amount' => 75,
        ],
        'voucher_code' => $voucher->code,
        'reference_id' => 'PAYNAMICS-AUTH-123',
        'provider' => 'paynamics',
        'currency' => 'PHP',
    ]);

    expect($result)->toMatchArray([
        'driver' => 'withdrawal_otp',
        'requested' => true,
        'target' => '639171234567',
    ])
        ->and($result)->toHaveKey('reference')
        ->and($result)->toHaveKey('meta');
});
