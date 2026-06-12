<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\ClaimOtpChallengeContract;
use LBHurtado\XChange\Contracts\WithdrawalOtpApprovalServiceContract;
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

it('delegates OTP challenge request to the withdrawal OTP approval service', function () {
    $voucher = issueVoucher();

    $otp = new class implements WithdrawalOtpApprovalServiceContract
    {
        public array $request = [];

        public array $verify = [];

        public function request(string $mobile, string $reference, array $context = []): array
        {
            $this->request = compact('mobile', 'reference', 'context');

            return [
                'reference' => 'WITHDRAWAL-OTP-123',
                'provider' => 'paynamics',
                'message' => 'OTP requested.',
            ];
        }

        public function verify(string $mobile, string $reference, string $code, array $context = []): bool
        {
            $this->verify = compact('mobile', 'reference', 'code', 'context');

            return false;
        }
    };

    app()->instance(WithdrawalOtpApprovalServiceContract::class, $otp);

    $service = app(WithdrawalOtpApprovalBackedClaimOtpChallengeService::class);

    $result = $service->request($voucher, [
        'payload' => [
            'mobile' => '639171234567',
            'amount' => 75,
        ],
        'voucher_code' => $voucher->code,
    ]);

    expect($otp->request)->toMatchArray([
        'mobile' => '639171234567',
        'reference' => $voucher->code,
    ])
        ->and(data_get($otp->request, 'context.amount'))->toBe(75)
        ->and(data_get($otp->request, 'context.voucher_code'))->toBe($voucher->code)
        ->and($result)->toMatchArray([
            'driver' => 'withdrawal_otp',
            'requested' => true,
            'reference' => 'WITHDRAWAL-OTP-123',
            'target' => '639171234567',
        ])
        ->and(data_get($result, 'meta.provider'))->toBe('paynamics');
});
