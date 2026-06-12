<?php

declare(strict_types=1);

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Claim\SubmitClaimApprovalOtp;
use LBHurtado\XChange\Contracts\ClaimOtpVerificationContract;

it('submits claim approval OTP through the active configured OTP driver', function () {
    $voucher = issueVoucher();

    $verifier = new class implements ClaimOtpVerificationContract
    {
        public ?Voucher $voucher = null;

        public ?string $code = null;

        public array $workflow = [];

        public function verify(Voucher $voucher, string $code, array $workflow): bool
        {
            $this->voucher = $voucher;
            $this->code = $code;
            $this->workflow = $workflow;

            return true;
        }
    };

    $verifierClass = $verifier::class;

    app()->instance($verifierClass, $verifier);

    config()->set('x-change.claim_approval.otp.driver', 'withdrawal_otp');
    config()->set('x-change.claim_approval.otp.drivers.withdrawal_otp.verify', $verifierClass);

    $result = app(SubmitClaimApprovalOtp::class)->handle($voucher, [
        'otp' => '441498',
        'reference_id' => 'PAYNAMICS-AUTH-123',
        'provider' => 'paynamics',
    ]);

    expect($verifier->voucher?->is($voucher))->toBeTrue()
        ->and($verifier->code)->toBe('441498')
        ->and($verifier->workflow)->toMatchArray([
            'otp' => '441498',
            'reference_id' => 'PAYNAMICS-AUTH-123',
            'provider' => 'paynamics',
        ])
        ->and($result)->toMatchArray([
            'status' => 'completed',
            'voucher_code' => $voucher->code,
            'reference_id' => 'PAYNAMICS-AUTH-123',
            'provider' => 'paynamics',
            'messages' => [
                'Approval OTP verified.',
            ],
        ])
        ->and(data_get($result, 'approval_metadata'))->toMatchArray([
            'provider' => 'paynamics',
            'authorization_type' => 'otp',
            'reference_id' => 'PAYNAMICS-AUTH-123',
            'otp_required' => false,
            'message' => 'Approval OTP verified.',
        ]);
});

it('keeps claim approval pending when configured OTP driver verification fails', function () {
    $voucher = issueVoucher();

    $verifier = new class implements ClaimOtpVerificationContract
    {
        public function verify(Voucher $voucher, string $code, array $workflow): bool
        {
            return false;
        }
    };

    $verifierClass = $verifier::class;

    app()->instance($verifierClass, $verifier);

    config()->set('x-change.claim_approval.otp.driver', 'withdrawal_otp');
    config()->set('x-change.claim_approval.otp.drivers.withdrawal_otp.verify', $verifierClass);

    $result = app(SubmitClaimApprovalOtp::class)->handle($voucher, [
        'otp' => '000000',
        'reference_id' => 'PAYNAMICS-AUTH-123',
        'provider' => 'paynamics',
    ]);

    expect($result)->toMatchArray([
        'status' => 'received',
        'voucher_code' => $voucher->code,
        'reference_id' => 'PAYNAMICS-AUTH-123',
        'provider' => 'paynamics',
        'messages' => [
            'Approval OTP received.',
        ],
    ])
        ->and(data_get($result, 'approval_metadata'))->toMatchArray([
            'provider' => 'paynamics',
            'authorization_type' => 'otp',
            'reference_id' => 'PAYNAMICS-AUTH-123',
            'otp_required' => true,
            'message' => 'Approval OTP received.',
        ]);
});
