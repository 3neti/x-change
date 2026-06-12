<?php

declare(strict_types=1);

use LBHurtado\EmiCore\Contracts\PayoutProvider;
use LBHurtado\XChange\Support\Claim\ClaimApprovalOtpAuthorizerResolver;
use LBHurtado\XChange\Support\Claim\ClaimApprovalProviderNormalizer;

it('normalizes claim approval OTP payload provider from active payout provider binding', function () {
    $voucher = issueVoucher();

    $provider = new class
    {
        public function disburse(...$arguments): mixed
        {
            return null;
        }
    };

    app()->instance(PayoutProvider::class, $provider);

    $resolver = new ClaimApprovalOtpAuthorizerResolver(
        new ClaimApprovalProviderNormalizer(),
    );

    $payload = $resolver->normalizePayload($voucher, [
        'otp' => '123456',
    ]);

    expect($payload)->toHaveKey('provider')
        ->and($payload['provider'])->toBe(strtolower($provider::class));
});

it('prefers explicit claim approval OTP payload provider over active payout provider binding', function () {
    $voucher = issueVoucher();

    app()->instance(PayoutProvider::class, new class
    {
        public function disburse(...$arguments): mixed
        {
            return null;
        }
    });

    $resolver = new ClaimApprovalOtpAuthorizerResolver(
        new ClaimApprovalProviderNormalizer(),
    );

    $payload = $resolver->normalizePayload($voucher, [
        'otp' => '123456',
        'provider' => 'paynamics',
    ]);

    expect($payload['provider'])->toBe('paynamics');
});

it('prefers voucher payout provider metadata over active payout provider binding', function () {
    $voucher = issueVoucher();
    $voucher->metadata = [
        'payout' => [
            'provider' => 'paynamics',
        ],
    ];

    app()->instance(PayoutProvider::class, new class
    {
        public function disburse(...$arguments): mixed
        {
            return null;
        }
    });

    $resolver = new ClaimApprovalOtpAuthorizerResolver(
        new ClaimApprovalProviderNormalizer(),
    );

    $payload = $resolver->normalizePayload($voucher, [
        'otp' => '123456',
    ]);

    expect($payload['provider'])->toBe('paynamics');
});
