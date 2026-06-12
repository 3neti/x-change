<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\ClaimOtpVerificationContract;
use LBHurtado\XChange\Contracts\WithdrawalOtpApprovalServiceContract;
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

it('delegates OTP verification to the withdrawal OTP approval service', function () {
    $voucher = issueVoucher();

    $otp = new class implements WithdrawalOtpApprovalServiceContract
    {
        public array $request = [];

        public array $verify = [];

        public function request(string $mobile, string $reference, array $context = []): array
        {
            $this->request = compact('mobile', 'reference', 'context');

            return [];
        }

        public function verify(string $mobile, string $reference, string $code, array $context = []): bool
        {
            $this->verify = compact('mobile', 'reference', 'code', 'context');

            return $code === '441498';
        }
    };

    app()->instance(WithdrawalOtpApprovalServiceContract::class, $otp);

    $service = app(WithdrawalOtpApprovalBackedClaimOtpVerificationService::class);

    $result = $service->verify($voucher, '441498', [
        'payload' => [
            'mobile' => '639171234567',
        ],
        'voucher_code' => $voucher->code,
    ]);

    expect($result)->toBeTrue()
        ->and($otp->verify)->toMatchArray([
            'mobile' => '639171234567',
            'reference' => $voucher->code,
            'code' => '441498',
        ])
        ->and(data_get($otp->verify, 'context.voucher_code'))->toBe($voucher->code);
});
