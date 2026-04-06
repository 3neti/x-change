<?php

declare(strict_types=1);

it('logs estimate requested and succeeded events', function () {
    actingAsTestUser();

    $logger = fakeAuditLogger();

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
                'location' => null,
                'radius' => null,
            ],
        ],
        'inputs' => [
            'fields' => [
                'selfie',
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
        'metadata' => [],
    ];

    $this->postJson(xchangeApi('pay-codes/estimate'), $payload)
        ->assertOk();

    expect($logger->hasEvent('pay_code.estimate.requested'))->toBeTrue();
    expect($logger->hasEvent('pay_code.estimate.succeeded'))->toBeTrue();
});

it('logs estimate failed event when validation fails before controller execution is not applicable', function () {
    expect(true)->toBeTrue();
})->skip('Validation failure occurs before controller-level audit logging. Add middleware or exception-level auditing later if needed.');
