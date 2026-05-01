<?php

declare(strict_types=1);

use LBHurtado\XChange\Models\VoucherCollection;
use LBHurtado\XChange\Tests\Fakes\User as FakeLifecycleUser;

beforeEach(function () {
    config([
        'x-change.lifecycle.defaults.user_model' => FakeLifecycleUser::class,
        'x-change.onboarding.issuer_model' => FakeLifecycleUser::class,
    ]);
});

it('handles manual payment webhook through lifecycle api', function () {
    $issuer = actingAsTestUser(1_000_000);

    $wallet = $issuer->wallet;
    $balanceBefore = (float) $wallet->balanceFloat;

    $voucher = issueVoucher(validVoucherInstructions(
        amount: 0.00,
        settlementRail: 'INSTAPAY',
        overrides: [
            'target_amount' => 100.00,
            'metadata' => [
                'flow_type' => 'collectible',
                'issuer_id' => (string) $issuer->id,
            ],
        ],
    ));

    $response = $this->postJson(route('api.x.v1.payment.webhooks.handle', [
        'provider' => 'manual',
    ]), [
        'voucher_code' => $voucher->code,
        'amount' => 100.00,
        'currency' => 'PHP',
        'status' => 'succeeded',
        'provider_reference' => 'REF-WEBHOOK-API-1',
        'provider_transaction_id' => 'TXN-WEBHOOK-API-1',
        'event_id' => 'evt-api-1',
    ]);

    $response->assertOk();
    $response->assertJsonPath('success', true);
    $response->assertJsonPath('data.status', 'collected');
    $response->assertJsonPath('data.voucher_code', $voucher->code);

    expect((float) $wallet->fresh()->balanceFloat)->toBe($balanceBefore + 100.00);
    expect(VoucherCollection::query()->where('voucher_id', $voucher->id)->count())->toBe(1);
});

it('replays duplicate manual webhook through lifecycle api', function () {
    $issuer = actingAsTestUser(1_000_000);

    $wallet = $issuer->wallet;
    $balanceBefore = (float) $wallet->balanceFloat;

    $voucher = issueVoucher(validVoucherInstructions(
        amount: 0.00,
        settlementRail: 'INSTAPAY',
        overrides: [
            'target_amount' => 100.00,
            'metadata' => [
                'flow_type' => 'collectible',
                'issuer_id' => (string) $issuer->id,
            ],
        ],
    ));

    $payload = [
        'voucher_code' => $voucher->code,
        'amount' => 100.00,
        'currency' => 'PHP',
        'status' => 'succeeded',
        'provider_reference' => 'REF-WEBHOOK-API-2',
        'provider_transaction_id' => 'TXN-WEBHOOK-API-2',
        'event_id' => 'evt-api-2',
    ];

    $first = $this->postJson(route('api.x.v1.payment.webhooks.handle', [
        'provider' => 'manual',
    ]), $payload);

    $second = $this->postJson(route('api.x.v1.payment.webhooks.handle', [
        'provider' => 'manual',
    ]), $payload);

    $first->assertOk();
    $second->assertOk();
    $second->assertJsonPath('data.meta.replayed', true);

    expect((float) $wallet->fresh()->balanceFloat)->toBe($balanceBefore + 100.00);
    expect(VoucherCollection::query()->where('voucher_id', $voucher->id)->count())->toBe(1);
});

it('returns not found when webhook voucher code is unknown', function () {
    $response = $this->postJson(route('api.x.v1.payment.webhooks.handle', [
        'provider' => 'manual',
    ]), [
        'voucher_code' => 'MISSING',
        'amount' => 100.00,
        'currency' => 'PHP',
        'status' => 'succeeded',
        'event_id' => 'evt-missing',
    ]);

    $response->assertNotFound();
});

it('returns server error for unsupported webhook provider for now', function () {
    $response = $this->postJson(route('api.x.v1.payment.webhooks.handle', [
        'provider' => 'missing',
    ]), [
        'voucher_code' => 'ANY',
        'amount' => 100.00,
        'currency' => 'PHP',
        'status' => 'succeeded',
        'event_id' => 'evt-missing-provider',
    ]);

    $response->assertStatus(500);
});
