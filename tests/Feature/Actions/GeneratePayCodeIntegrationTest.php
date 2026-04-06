<?php

declare(strict_types=1);

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\PayCode\GeneratePayCode;
use LBHurtado\XChange\Exceptions\InsufficientWalletBalance;

it('generates a pay code end to end and debits the issuer wallet', function () {
    $user = actingAsTestUser(1_000_000);

    config()->set('app.url', 'https://example.test');

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

    $action = app(GeneratePayCode::class);

    $result = $action->handle($payload);

    expect($result)->toHaveKeys([
        'voucher_id',
        'code',
        'amount',
        'currency',
        'issuer',
        'cost',
        'wallet',
        'debit',
        'links',
    ]);

    expect($result['amount'])->toBe(100.0);
    expect($result['currency'])->toBe('PHP');
    expect($result['issuer']['id'])->toBe($user->id);
    expect($result['cost']['total'])->toBeGreaterThan(0);

    expect($result['links']['redeem'])->toContain($result['code']);
    expect($result['links']['redeem_path'])->toContain($result['code']);

    $wallet->refresh();

    expect((float) $result['wallet']['balance_before'])->toBe($balanceBefore);
    expect((float) $result['wallet']['balance_after'])->toBeLessThan($balanceBefore);
    expect((float) $wallet->balance)->toBe((float) $result['wallet']['balance_after']);

    expect($result['debit'])->toBeArray();
    expect($result['debit'])->toHaveKey('id');

    $voucher = Voucher::query()->find($result['voucher_id']);

    expect($voucher)->not->toBeNull();
    expect($voucher?->code)->toBe($result['code']);
    expect($voucher?->instructions)->not->toBeNull();
    expect(data_get($voucher?->instructions, 'cash.amount'))->toBe(100.0);
});

it('fails end to end when issuer wallet cannot afford pay code generation', function () {
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

    $action = app(GeneratePayCode::class);

    expect(fn () => $action->handle($payload))
        ->toThrow(InsufficientWalletBalance::class);
});
