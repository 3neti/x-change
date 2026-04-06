<?php

declare(strict_types=1);

use LBHurtado\Voucher\Models\Voucher;

it('generates a pay code end to end via api and debits the issuer wallet', function () {
    $user = actingAsTestUser(1_000_000);

    $wallet = $user->wallet()->where('slug', 'platform')->first();
    expect($wallet)->not->toBeNull();

    $balanceBefore = (float) $wallet->balance;

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
        'metadata' => [],
    ];

    $response = $this->postJson(xchangeApi('pay-codes'), $payload);

    $response
        ->assertCreated()
        ->assertJsonStructure([
            'success',
            'data' => [
                'voucher_id',
                'code',
                'amount',
                'currency',
                'cost' => [
                    'currency',
                    'base_fee',
                    'components',
                    'total',
                ],
                'wallet' => [
                    'balance_before',
                    'balance_after',
                ],
                'debit',
            ],
            'meta',
        ]);

    $response->assertJson([
        'success' => true,
    ]);

    $data = $response->json('data');

    expect((float) $data['amount'])->toBe(100.0);
    expect($data['currency'])->toBe('PHP');
    expect((float) $data['wallet']['balance_before'])->toBe($balanceBefore);
    expect((float) $data['wallet']['balance_after'])->toBeLessThan($balanceBefore);

    $wallet->refresh();
    expect((float) $wallet->balance)->toBe((float) $data['wallet']['balance_after']);

    $voucher = Voucher::query()->find($data['voucher_id']);

    expect($voucher)->not->toBeNull();
    expect($voucher?->code)->toBe($data['code']);
    expect($voucher?->instructions)->not->toBeNull();
    expect(data_get($voucher?->instructions, 'cash.amount'))->toBe(100.0);
});

it('fails via api when issuer wallet cannot afford pay code generation', function () {
    actingAsTestUser(0);

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

    $response = $this->postJson(xchangeApi('pay-codes'), $payload);

    $response
        ->assertUnprocessable()
        ->assertJson([
            'success' => false,
            'code' => 'INSUFFICIENT_WALLET_BALANCE',
        ]);
});
