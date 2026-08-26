<?php

declare(strict_types=1);

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\PayCode\GeneratePayCode;
use LBHurtado\XChange\Exceptions\InsufficientWalletBalance;
use LBHurtado\XChange\Services\Commercial\ProvisionCommercialBaselines;
use LBHurtado\XChange\Tests\Fakes\User as FakeLifecycleUser;

beforeEach(function () {
    config([
        'x-change.lifecycle.defaults.user_model' => FakeLifecycleUser::class,
        'x-change.onboarding.issuer_model' => FakeLifecycleUser::class,
        'x-change.commercial.legal_trace.legal_entity_reference' => 'legal-entity:x-change:test',
        'x-change.commercial.legal_trace.profile_version' => 'test-v1',
    ]);

    $catalog = config('x-commerce.catalogs.pay_code');
    $catalog['version'] = 4;
    $catalog['items']['voucher_type.payable']['unit_price_minor'] = 0;
    $catalog['items']['voucher_type.settlement']['unit_price_minor'] = 0;
    config()->set('x-commerce.catalogs.pay_code', $catalog);

    app(ProvisionCommercialBaselines::class)
        ->provision('commissioning-manifest:collectible-issuance-cost');
});

it('does not debit target amount when generating collectible pay code', function () {
    $issuer = actingAsTestUser(1_000_000);

    $wallet = $issuer->wallet;
    $balanceBefore = (float) $wallet->balanceFloat;

    $result = app(GeneratePayCode::class)->handle([
        'issuer_id' => $issuer->id,

        'cash' => [
            'amount' => 100.00,
            'currency' => 'PHP',
            'settlement_rail' => 'INSTAPAY',
            'validation' => [
                'country' => 'PH',
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
        'prefix' => 'PAY',
        'mask' => '****',
        'ttl' => null,

        'metadata' => [
            'flow_type' => 'collectible',
            'collection_wallet_id' => (string) $wallet->getKey(),
        ],
    ]);

    $balanceAfter = (float) $wallet->fresh()->balanceFloat;
    $debited = $balanceBefore - $balanceAfter;

    expect($result->code)->not->toBeEmpty();

    expect($debited)->toBeLessThan(100.00);
});

it('requires only the commercial charge when issuing a collection voucher', function (string $voucherType): void {
    $issuer = actingAsTestUser(150);
    $wallet = $issuer->wallet()->where('slug', 'platform')->firstOrFail();
    $payload = validPayCodePayload(250, 'INSTAPAY', [
        'voucher_type' => $voucherType,
        'target_amount' => 250,
        'feedback' => [
            'email' => 'payer@example.test',
            'mobile' => null,
            'webhook' => null,
        ],
        'metadata' => [
            'flow_type' => 'collectible',
            'collection_wallet_id' => (string) $wallet->getKey(),
        ],
    ]);
    data_set($payload, 'inputs.fields', []);

    $result = app(GeneratePayCode::class)->handle([
        ...$payload,
        'issuer_id' => $issuer->getKey(),
    ]);
    $voucher = Voucher::query()->findOrFail($result->voucher_id);

    expect($result->code)->not->toBeEmpty()
        ->and($result->links->pay_path)->toBe(route('x-change.pay.show', ['code' => $result->code], false))
        ->and($result->links->pay)->toBe(route('x-change.pay.show', ['code' => $result->code]))
        ->and($result->cost->pay_code_value)->toBe(0.0)
        ->and($result->cost->total)->toBe(1.5)
        ->and($result->cost->account_debit)->toBe(1.5)
        ->and($voucher->instructions->target_amount)->toBe(250.0)
        ->and($voucher->instructions->cash->amount)->toBe(0.0)
        ->and((int) $wallet->refresh()->balanceInt)->toBe(0);
})->with(['payable', 'settlement']);

it('rejects a collection voucher when its issuer cannot cover the commercial charge', function (string $voucherType): void {
    $issuer = actingAsTestUser(149);
    $wallet = $issuer->wallet()->where('slug', 'platform')->firstOrFail();

    expect(fn () => app(GeneratePayCode::class)->handle([
        ...validPayCodePayload(250, 'INSTAPAY', [
            'voucher_type' => $voucherType,
            'target_amount' => 250,
            'inputs' => ['fields' => []],
            'feedback' => [
                'email' => 'payer@example.test',
                'mobile' => null,
                'webhook' => null,
            ],
            'metadata' => [
                'flow_type' => 'collectible',
                'collection_wallet_id' => (string) $wallet->getKey(),
            ],
        ]),
        'issuer_id' => $issuer->getKey(),
    ]))->toThrow(InsufficientWalletBalance::class);

    expect((int) $wallet->refresh()->balanceInt)->toBe(149);
})->with(['payable', 'settlement']);

it('still requires the face value and commercial charge for a redeemable voucher', function (): void {
    $issuer = actingAsTestUser(150);

    expect(fn () => app(GeneratePayCode::class)->handle([
        ...validPayCodePayload(250, 'INSTAPAY', [
            'inputs' => ['fields' => []],
            'feedback' => [
                'email' => 'payer@example.test',
                'mobile' => null,
                'webhook' => null,
            ],
        ]),
        'issuer_id' => $issuer->getKey(),
    ]))->toThrow(InsufficientWalletBalance::class);
});
