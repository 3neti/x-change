<?php

declare(strict_types=1);

use LBHurtado\XChange\Actions\PayCode\GeneratePayCode;
use LBHurtado\XChange\Exceptions\PayCodeIssuanceFailed;

it('logs generate requested and succeeded events', function () {
    $user = actingAsTestUser(1_000_000);

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

        'issuer_id' => $user->id,
    ];

    $this->postJson(xchangeApi('pay-codes'), $payload)
        ->assertCreated();

    expect($logger->hasEvent('pay_code.generate.requested'))->toBeTrue();
    expect($logger->hasEvent('pay_code.generate.succeeded'))->toBeTrue();
});

it('logs generate failed event when generation throws', function () {
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
            'fields' => [],
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

    $action = Mockery::mock(GeneratePayCode::class);
    $action->shouldReceive('handle')
        ->once()
        ->andThrow(new PayCodeIssuanceFailed('Pay Code issuance did not return a voucher.'));

    $this->app->instance(GeneratePayCode::class, $action);

    $this->postJson(xchangeApi('pay-codes'), $payload)
        ->assertStatus(500);

    expect($logger->hasEvent('pay_code.generate.requested'))->toBeTrue();
    expect($logger->hasEvent('pay_code.generate.failed'))->toBeTrue();
});
