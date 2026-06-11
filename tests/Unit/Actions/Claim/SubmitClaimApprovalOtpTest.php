<?php

declare(strict_types=1);

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Claim\SubmitClaimApprovalOtp;
use LBHurtado\XChange\Contracts\Claim\ClaimApprovalOtpAuthorizer;

it('returns received approval OTP result for voucher', function () {
    $voucher = issueVoucher();

    $result = app(SubmitClaimApprovalOtp::class)->handle($voucher, [
        'otp' => '123456',
        'reference_id' => 'AUTH-123',
        'provider' => 'payanamics',
    ]);

    expect($result)->toBe([
        'status' => 'received',
        'voucher_code' => $voucher->code,
        'reference_id' => 'AUTH-123',
        'provider' => 'payanamics',
        'messages' => [
            'Approval OTP received.',
        ],
        'approval_metadata' => [
            'provider' => 'payanamics',
            'authorization_type' => 'otp',
            'reference_id' => 'AUTH-123',
            'expires_at' => null,
            'otp_required' => true,
            'polling_required' => false,
            'manual_review' => false,
            'message' => 'Approval OTP received.',
        ],
    ]);
});

it('defaults optional approval OTP metadata to null', function () {
    $voucher = issueVoucher();

    $result = app(SubmitClaimApprovalOtp::class)->handle($voucher, [
        'otp' => '123456',
    ]);

    expect($result)->toBe([
        'status' => 'received',
        'voucher_code' => $voucher->code,
        'reference_id' => null,
        'provider' => null,
        'messages' => [
            'Approval OTP received.',
        ],
        'approval_metadata' => [
            'provider' => null,
            'authorization_type' => 'otp',
            'reference_id' => null,
            'expires_at' => null,
            'otp_required' => true,
            'polling_required' => false,
            'manual_review' => false,
            'message' => 'Approval OTP received.',
        ],
    ]);
});

it('delegates approval OTP authorization to configured authorizer', function () {
    $voucher = issueVoucher();

    $authorizer = new class implements ClaimApprovalOtpAuthorizer
    {
        public ?Voucher $voucher = null;

        public array $payload = [];

        public function authorize(Voucher $voucher, array $payload): array
        {
            $this->voucher = $voucher;
            $this->payload = $payload;

            return [
                'status' => 'completed',
                'voucher_code' => (string) $voucher->code,
                'reference_id' => $payload['reference_id'] ?? null,
                'provider' => $payload['provider'] ?? null,
                'messages' => ['OTP verified.'],
            ];
        }
    };

    $result = app(SubmitClaimApprovalOtp::class, [
        'authorizer' => $authorizer,
    ])->handle($voucher, [
        'otp' => '123456',
        'reference_id' => 'AUTH-123',
        'provider' => 'payanamics',
    ]);

    expect($authorizer->voucher?->is($voucher))->toBeTrue()
        ->and($authorizer->payload)->toMatchArray([
            'otp' => '123456',
            'reference_id' => 'AUTH-123',
            'provider' => 'payanamics',
        ])
        ->and($result)->toBe([
            'status' => 'completed',
            'voucher_code' => $voucher->code,
            'reference_id' => 'AUTH-123',
            'provider' => 'payanamics',
            'messages' => ['OTP verified.'],
        ]);
});

it('uses bound provider authorizer for Paynamics OTP approval', function () {
    $voucher = issueVoucher();

    $this->app->bind(
        \LBHurtado\XChange\Contracts\Claim\ClaimApprovalOtpAuthorizer::class,
        fn () => new class implements \LBHurtado\XChange\Contracts\Claim\ClaimApprovalOtpAuthorizer
        {
            public function authorize(\LBHurtado\Voucher\Models\Voucher $voucher, array $payload): array
            {
                expect($payload)->toMatchArray([
                    'otp' => '123456',
                    'reference_id' => 'PAYNAMICS-AUTH-123',
                    'provider' => 'payanamics',
                ]);

                return [
                    'status' => 'completed',
                    'voucher_code' => (string) $voucher->code,
                    'claim_type' => 'withdraw',
                    'claimed' => true,
                    'requested_amount' => null,
                    'disbursed_amount' => 1000,
                    'currency' => 'PHP',
                    'remaining_balance' => 0,
                    'fully_claimed' => true,
                    'reference_id' => 'PAYNAMICS-AUTH-123',
                    'provider' => 'payanamics',
                    'messages' => ['Paynamics OTP verified.'],
                    'approval_metadata' => [
                        'provider' => 'payanamics',
                        'authorization_type' => 'otp',
                        'reference_id' => 'PAYNAMICS-AUTH-123',
                        'expires_at' => null,
                        'otp_required' => false,
                        'polling_required' => false,
                        'manual_review' => false,
                        'message' => 'Paynamics OTP verified.',
                    ],
                ];
            }
        }
    );

    $result = app(\LBHurtado\XChange\Actions\Claim\SubmitClaimApprovalOtp::class)
        ->handle($voucher, [
            'otp' => '123456',
            'reference_id' => 'PAYNAMICS-AUTH-123',
            'provider' => 'payanamics',
        ]);

    expect($result)->toMatchArray([
        'status' => 'completed',
        'voucher_code' => $voucher->code,
        'provider' => 'payanamics',
        'reference_id' => 'PAYNAMICS-AUTH-123',
        'messages' => ['Paynamics OTP verified.'],
    ])
        ->and(data_get($result, 'approval_metadata'))->toMatchArray([
            'provider' => 'payanamics',
            'authorization_type' => 'otp',
            'reference_id' => 'PAYNAMICS-AUTH-123',
            'otp_required' => false,
            'message' => 'Paynamics OTP verified.',
        ]);

});

