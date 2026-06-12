<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\WithdrawalOtpApprovalServiceContract;
use LBHurtado\XChange\Services\PaynamicsWithdrawalOtpApprovalService;

it('implements the withdrawal OTP approval service contract', function () {
    expect(app(PaynamicsWithdrawalOtpApprovalService::class))
        ->toBeInstanceOf(WithdrawalOtpApprovalServiceContract::class);
});

it('returns Paynamics OTP request metadata', function () {
    $service = app(PaynamicsWithdrawalOtpApprovalService::class);

    $result = $service->request(
        mobile: '639171234567',
        reference: 'TEST-Z3EL',
        context: [
            'voucher_code' => 'TEST-Z3EL',
            'amount' => 75,
        ],
    );

    expect($result)->toMatchArray([
        'provider' => 'paynamics',
        'reference' => 'TEST-Z3EL',
        'requested' => true,
        'target' => '********4567',
        'message' => 'Paynamics payout OTP requested.',
        'context' => [
            'voucher_code' => 'TEST-Z3EL',
            'amount' => 75,
        ],
    ]);
});

it('does not verify Paynamics OTP until a concrete provider adapter is wired', function () {
    $service = app(PaynamicsWithdrawalOtpApprovalService::class);

    expect($service->verify(
        mobile: '639171234567',
        reference: 'TEST-Z3EL',
        code: '441498',
        context: [
            'voucher_code' => 'TEST-Z3EL',
        ],
    ))->toBeFalse();
});
