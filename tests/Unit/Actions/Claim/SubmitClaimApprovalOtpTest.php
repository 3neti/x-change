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
