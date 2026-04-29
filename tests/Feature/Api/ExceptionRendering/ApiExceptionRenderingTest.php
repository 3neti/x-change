<?php

declare(strict_types=1);

it('maps missing voucher to voucher not found response', function () {
    $response = $this->getJson(route('api.x.v1.vouchers.payment-qr.show', [
        'code' => 'MISSING',
    ]));

    $response->assertNotFound();
    $response->assertJsonPath('success', false);
    $response->assertJsonPath('code', 'VOUCHER_NOT_FOUND');
});

it('maps collectible claim submission block to cannot disburse response', function () {
    $voucher = issueVoucher(validVoucherInstructions(100.00, 'INSTAPAY', [
        'voucher_type' => 'payable',
    ]));

    $response = $this->postJson(route('api.x.v1.vouchers.claim.submit', [
        'code' => $voucher->code,
    ]), [
        'mobile' => '09171234567',
        'recipient_country' => 'PH',
        'amount' => 100,
        'inputs' => [],
        'bank_account' => [
            'bank_code' => 'GXCHPHM2XXX',
            'account_number' => '09171234567',
        ],
    ]);

    $response->assertStatus(422);
    $response->assertJsonPath('success', false);
    $response->assertJsonPath('code', 'VOUCHER_CANNOT_DISBURSE');
    $response->assertJsonPath('errors.type', 'capability_violation');
    $response->assertJsonPath('errors.flow', 'collectible');
    $response->assertJsonPath('errors.can_disburse', false);
});

it('maps disbursable payment qr block to cannot collect response', function () {
    $voucher = issueVoucher(validVoucherInstructions(100.00, 'INSTAPAY', [
        'voucher_type' => 'redeemable',
    ]));

    $response = $this->getJson(route('api.x.v1.vouchers.payment-qr.show', [
        'code' => $voucher->code,
    ]));

    $response->assertStatus(422);
    $response->assertJsonPath('success', false);
    $response->assertJsonPath('code', 'VOUCHER_CANNOT_COLLECT');
    $response->assertJsonPath('errors.type', 'capability_violation');
    $response->assertJsonPath('errors.flow', 'disbursable');
    $response->assertJsonPath('errors.can_collect', false);
});

it('maps validation exceptions to validation error response', function () {
    $response = $this->postJson(route('api.x.v1.vouchers.claim.submit', [
        'code' => 'ANY-CODE',
    ]), []);

    $response->assertStatus(422);
    $response->assertJsonPath('success', false);
    $response->assertJsonPath('code', 'VALIDATION_ERROR');
    $response->assertJsonPath('message', 'The given data was invalid.');
    $response->assertJsonValidationErrors([
        'mobile',
        'inputs',
        'bank_account',
        'bank_account.bank_code',
        'bank_account.account_number',
    ]);
});

it('maps idempotency conflicts to conflict response', function () {
    $user = actingAsTestUser(1_000_000);

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

        'issuer_id' => $user->id,
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

        'issuer_id' => $user->id,
    ];

    $first = $this->postJson(route('api.x.v1.vouchers.store'), $firstPayload, $headers);
    $first->assertSuccessful();

    $second = $this->postJson(route('api.x.v1.vouchers.store'), $secondPayload, $headers);

    $second->assertStatus(409);
    $second->assertJsonPath('success', false);
    $second->assertJsonPath('code', 'IDEMPOTENCY_CONFLICT');
});
