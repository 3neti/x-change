<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use LBHurtado\XChange\Tests\Fakes\User as FakeLifecycleUser;

beforeEach(function () {
    config([
        'x-change.lifecycle.defaults.user_model' => FakeLifecycleUser::class,
        'x-change.onboarding.issuer_model' => FakeLifecycleUser::class,
    ]);

    Artisan::call('xchange:lifecycle:prepare', [
        '--seed' => true,
    ]);
});

it('confirms collectible voucher payment and credits issuer wallet', function () {
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

    $response = $this->postJson(route('api.x.v1.vouchers.payment-confirmations.store', [
        'code' => $voucher->code,
    ]), [
        'amount' => 100.00,
        'currency' => 'PHP',
        'status' => 'succeeded',
        'provider' => 'manual',
        'provider_reference' => 'REF-123',
        'provider_transaction_id' => 'TXN-123',
        'payer' => [
            'name' => 'Juan Dela Cruz',
            'mobile' => '09171234567',
        ],
    ]);

    $response->assertOk();
    $response->assertJsonPath('success', true);
    $response->assertJsonPath('data.status', 'collected');
    $response->assertJsonPath('data.voucher_code', $voucher->code);
    $response->assertJsonPath('data.amount', 100);

    expect((float) $wallet->fresh()->balanceFloat)
        ->toBe($balanceBefore + 100.00);
});

it('blocks payment confirmation for disbursable voucher', function () {
    $issuer = actingAsTestUser(1_000_000);

    $voucher = issueVoucher(validVoucherInstructions(
        amount: 100.00,
        settlementRail: 'INSTAPAY',
        overrides: [
            'metadata' => [
                'flow_type' => 'disbursable',
                'issuer_id' => (string) $issuer->id,
            ],
        ],
    ));

    $response = $this->postJson(route('api.x.v1.vouchers.payment-confirmations.store', [
        'code' => $voucher->code,
    ]), [
        'amount' => 100.00,
        'currency' => 'PHP',
        'status' => 'succeeded',
    ]);

    $response->assertStatus(422);
    $response->assertJsonPath('success', false);
    $response->assertJsonPath('code', 'VOUCHER_CANNOT_COLLECT');
});

it('validates payment confirmation payload', function () {
    $issuer = actingAsTestUser(1_000_000);

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

    $response = $this->postJson(route('api.x.v1.vouchers.payment-confirmations.store', [
        'code' => $voucher->code,
    ]), []);

    $response->assertStatus(422);
    $response->assertJsonPath('success', false);
    $response->assertJsonPath('code', 'VALIDATION_ERROR');
    $response->assertJsonValidationErrors(['amount']);
});

it('returns not found for unknown voucher code', function () {
    $response = $this->postJson(route('api.x.v1.vouchers.payment-confirmations.store', [
        'code' => 'MISSING',
    ]), [
        'amount' => 100.00,
        'currency' => 'PHP',
        'status' => 'succeeded',
    ]);

    $response->assertNotFound();
});
