<?php

declare(strict_types=1);

it('returns a pricing estimate via api', function () {
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

    $payload = validVoucherInstructions(100.0, 'INSTAPAY', [
        'inputs' => [
            'fields' => [
                'selfie',
            ],
        ],
    ])->toArray();

    $response = $this->postJson('/pay-codes/estimate', $payload);

    $response
        ->assertOk()
        ->assertJsonStructure([
            'success',
            'data' => [
                'currency',
                'base_fee',
                'components',
                'total',
            ],
            'meta',
        ]);

    $response->assertJson([
        'success' => true,
    ]);
});

it('validates required pricing payload fields', function () {
    $response = $this->postJson('/pay-codes/estimate', []);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'cash',
            'inputs',
            'feedback',
            'rider',
        ]);
});
