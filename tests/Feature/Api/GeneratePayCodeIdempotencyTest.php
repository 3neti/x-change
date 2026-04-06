<?php

declare(strict_types=1);

use LBHurtado\Voucher\Models\Voucher;

it('replays the same pay code response for the same idempotency key and payload', function () {
    actingAsTestUser(1_000_000);

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

    $headers = [
        'Idempotency-Key' => 'idem-001',
    ];

    $first = $this->postJson(xchangeApi('pay-codes'), $payload, $headers);
    $first->assertCreated();

    $second = $this->postJson(xchangeApi('pay-codes'), $payload, $headers);
    $second
        ->assertOk()
        ->assertJsonPath('meta.idempotency.replayed', true);

    expect($second->json('data'))->toBe($first->json('data'));
    expect(Voucher::query()->count())->toBe(1);
});

it('returns conflict when the same idempotency key is reused with a different payload', function () {
    actingAsTestUser(1_000_000);

    $headers = [
        'Idempotency-Key' => 'idem-002',
    ];

    $firstPayload = [
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

    $secondPayload = [
        'cash' => [
            'amount' => 200.0,
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

    $this->postJson(xchangeApi('pay-codes'), $firstPayload, $headers)
        ->assertCreated();

    $this->postJson(xchangeApi('pay-codes'), $secondPayload, $headers)
        ->assertStatus(409)
        ->assertJson([
            'success' => false,
            'code' => 'IDEMPOTENCY_CONFLICT',
        ]);
});
