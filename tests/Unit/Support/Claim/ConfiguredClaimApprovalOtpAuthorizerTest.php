<?php

declare(strict_types=1);

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\ClaimOtpVerificationContract;
use LBHurtado\XChange\Support\Claim\ConfiguredClaimApprovalOtpAuthorizer;

it('uses the active claim approval OTP verification driver', function () {
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

    config()->set('x-change.claim_approval.otp.driver', 'test_driver');
    config()->set('x-change.claim_approval.otp.drivers.test_driver.verify', $verifierClass);

    $result = app(ConfiguredClaimApprovalOtpAuthorizer::class)
        ->authorize($voucher, [
            'otp' => '123456',
            'reference_id' => 'AUTH-123',
            'provider' => 'paynamics',
        ]);

    expect($verifier->voucher?->is($voucher))->toBeTrue()
        ->and($verifier->code)->toBe('123456')
        ->and($verifier->workflow)->toMatchArray([
            'otp' => '123456',
            'reference_id' => 'AUTH-123',
            'provider' => 'paynamics',
        ])
        ->and($result)->toMatchArray([
            'status' => 'completed',
            'voucher_code' => $voucher->code,
            'reference_id' => 'AUTH-123',
            'provider' => 'paynamics',
            'messages' => [
                'Approval OTP verified.',
            ],
        ])
        ->and(data_get($result, 'approval_metadata'))->toMatchArray([
            'provider' => 'paynamics',
            'authorization_type' => 'otp',
            'reference_id' => 'AUTH-123',
            'otp_required' => false,
            'message' => 'Approval OTP verified.',
        ]);
});

it('falls back to the null claim approval OTP verifier when active driver has no verifier', function () {
    $voucher = issueVoucher();

    config()->set('x-change.claim_approval.otp.driver', 'missing_driver');

    $result = app(ConfiguredClaimApprovalOtpAuthorizer::class)
        ->authorize($voucher, [
            'otp' => '123456',
            'reference_id' => 'AUTH-123',
            'provider' => 'paynamics',
        ]);

    expect($result)->toMatchArray([
        'status' => 'received',
        'voucher_code' => $voucher->code,
        'reference_id' => 'AUTH-123',
        'provider' => 'paynamics',
    ]);
});
