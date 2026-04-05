<?php

declare(strict_types=1);

use LBHurtado\XChange\Actions\PayCode\EstimatePayCodeCost;

it('returns pricing estimate from voucher instructions input', function () {
    config()->set('x-change.pricing.base_fee', 1.0);
    config()->set('x-change.pricing.components', [
        'cash' => 0.0,
        'kyc' => 25.0,
        'otp' => 2.0,
        'selfie' => 5.0,
        'signature' => 3.0,
        'location' => 1.0,
        'webhook' => 4.0,
        'email_feedback' => 6.0,
        'sms_feedback' => 7.0,
    ]);

    $payload = [
        'cash' => [
            'amount' => 100.0,
            'currency' => 'PHP',
            'settlement_rail' => 'INSTAPAY',
            'validation' => [
                'secret' => null,
                'mobile' => null,
                'payable' => null,
                'country' => 'PH',
                'location' => '14.5995,120.9842',
                'radius' => '50',
            ],
        ],
        'inputs' => [
            'fields' => [
                'selfie',
                'signature',
            ],
        ],
        'feedback' => [
            'email' => 'example@example.com',
            'mobile' => '09171234567',
            'webhook' => 'https://example.com/webhook',
        ],
        'rider' => [
            'message' => null,
            'url' => null,
            'redirect_timeout' => null,
            'splash' => null,
            'splash_timeout' => null,
            'og_source' => null,
        ],
        'count' => 1,
        'prefix' => 'TEST',
        'mask' => '****',
        'ttl' => null,
        'metadata' => [
            'issuer_id' => null,
            'issuer_name' => null,
            'issuer_email' => null,
            'created_at' => now()->toIso8601String(),
            'issued_at' => now()->toIso8601String(),
        ],
    ];

    $action = app(EstimatePayCodeCost::class);

    $result = $action->handle($payload);

    expect($result)->toHaveKeys([
        'currency',
        'base_fee',
        'components',
        'total',
    ]);

    expect($result['currency'])->toBe('PHP');
    expect($result['base_fee'])->toBe(1.0);
    expect($result['components']['kyc'])->toBe(25.0);
    expect($result['components']['selfie'])->toBe(5.0);
    expect($result['components']['signature'])->toBe(3.0);
    expect($result['total'])->toBe(52.0);
});
