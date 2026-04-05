<?php

declare(strict_types=1);

use LBHurtado\XChange\Services\PricingService;

it('estimates only the base fee when no priced optional components are present', function () {
    config()->set('x-change.pricing.currency', 'PHP');
    config()->set('x-change.pricing.base_fee', 0.50);
    config()->set('x-change.pricing.components', [
        'cash' => 0.00,
        'kyc' => 25.00,
        'otp' => 2.00,
        'selfie' => 5.00,
        'signature' => 3.00,
        'location' => 1.00,
        'webhook' => 0.00,
        'email_feedback' => 0.00,
        'sms_feedback' => 0.00,
    ]);

    $instructions = validVoucherInstructions(100.00, 'INSTAPAY', [
        'feedback' => [
            'email' => null,
            'mobile' => null,
            'webhook' => null,
        ],
    ]);

    $service = new PricingService;

    $estimate = $service->estimate($instructions);

    expect($estimate['currency'])->toBe('PHP');
    expect($estimate['base_fee'])->toBe(0.50);
    expect($estimate['components']['kyc'])->toBe(0.0);
    expect($estimate['components']['otp'])->toBe(0.0);
    expect($estimate['components']['selfie'])->toBe(0.0);
    expect($estimate['components']['signature'])->toBe(0.0);
    expect($estimate['components']['location'])->toBe(0.0);
    expect($estimate['components']['webhook'])->toBe(0.0);
    expect($estimate['components']['email_feedback'])->toBe(0.0);
    expect($estimate['components']['sms_feedback'])->toBe(0.0);
    expect($estimate['total'])->toBe(0.50);
});

it('adds kyc, selfie, signature, location, webhook, email, and sms fees when present', function () {
    config()->set('x-change.pricing.currency', 'PHP');
    config()->set('x-change.pricing.base_fee', 1.00);
    config()->set('x-change.pricing.components', [
        'cash' => 0.00,
        'kyc' => 25.00,
        'otp' => 2.00,
        'selfie' => 5.00,
        'signature' => 3.00,
        'location' => 1.00,
        'webhook' => 4.00,
        'email_feedback' => 6.00,
        'sms_feedback' => 7.00,
    ]);

    $instructions = validVoucherInstructions(500.00, 'INSTAPAY', [
        'cash' => [
            'validation' => [
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
    ]);

    $service = new PricingService;
    $estimate = $service->estimate($instructions);

    expect($estimate['components']['kyc'])->toBe(25.0);
    expect($estimate['components']['selfie'])->toBe(5.0);
    expect($estimate['components']['signature'])->toBe(3.0);
    expect($estimate['components']['location'])->toBe(1.0);
    expect($estimate['components']['webhook'])->toBe(4.0);
    expect($estimate['components']['email_feedback'])->toBe(6.0);
    expect($estimate['components']['sms_feedback'])->toBe(7.0);
    expect($estimate['total'])->toBe(52.0);
});

it('adds otp fee when otp validation is present', function () {
    config()->set('x-change.pricing.base_fee', 0.00);
    config()->set('x-change.pricing.components', [
        'cash' => 0.00,
        'kyc' => 25.00,
        'otp' => 2.00,
        'selfie' => 5.00,
        'signature' => 3.00,
        'location' => 1.00,
        'webhook' => 0.00,
        'email_feedback' => 0.00,
        'sms_feedback' => 0.00,
    ]);

    $instructions = validVoucherInstructions(100.00, 'INSTAPAY', [
        'cash' => [
            'validation' => [
                'payable' => 'otp',
                'otp' => 'required',
            ],
        ],
        'feedback' => [
            'email' => null,
            'mobile' => null,
            'webhook' => null,
        ],
    ]);

    $service = new PricingService;

    $estimate = $service->estimate($instructions);

    expect($estimate['components']['otp'])->toBe(2.0);
    expect($estimate['total'])->toBe(2.0);
});
