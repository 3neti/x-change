<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use LBHurtado\EmiCore\Data\PayoutRequestData;
use LBHurtado\XChange\Exceptions\VoucherCannotDisburse;
use LBHurtado\XChange\Models\VoucherCollection;
use LBHurtado\XChange\Services\WithdrawalWalletSettlementService;
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

    $collection = VoucherCollection::query()
        ->where('voucher_id', $voucher->id)
        ->latest('id')
        ->first();

    expect($collection)->not->toBeNull()
        ->and($collection->status)->toBe('collected')
        ->and($collection->requested_amount_minor)->toBe(10000)
        ->and($collection->collected_amount_minor)->toBe(10000)
        ->and($collection->provider)->toBe('manual')
        ->and($collection->provider_reference)->toBe('REF-123')
        ->and($collection->provider_transaction_id)->toBe('TXN-123')
        ->and($collection->payer_mobile)->toBe('09171234567')
        ->and($collection->wallet_transaction_id)->not->toBeNull();
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

it('blocks collectible voucher wallet settlement', function () {
    $voucher = issueVoucher(validVoucherInstructions(
        amount: 0.00,
        settlementRail: 'INSTAPAY',
        overrides: [
            'target_amount' => 100.00,
            'metadata' => [
                'flow_type' => 'collectible',
            ],
        ],
    ));

    $input = new PayoutRequestData(
        reference: 'REF-123',
        bank_code: 'GXCHPHM2XXX',
        account_number: '09173011987',
        amount: 100.00,
        currency: 'PHP',
        settlement_rail: 'INSTAPAY',
    );

    app(WithdrawalWalletSettlementService::class)->settle(
        voucher: $voucher,
        input: $input,
        withdrawAmount: 100.00,
        sliceNumber: 1,
    );
})->throws(VoucherCannotDisburse::class);

it('does not double credit wallet when payment confirmation is replayed', function () {
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
        'amount' => 100.00,
        'currency' => 'PHP',
        'status' => 'succeeded',
        'provider' => 'manual',
        'provider_reference' => 'REF-API-IDEM-1',
        'provider_transaction_id' => 'TXN-API-IDEM-1',
        'idempotency_key' => 'api-idem-1',
    ];

    $first = $this->postJson(route('api.x.v1.vouchers.payment-confirmations.store', [
        'code' => $voucher->code,
    ]), $payload);

    $second = $this->postJson(route('api.x.v1.vouchers.payment-confirmations.store', [
        'code' => $voucher->code,
    ]), $payload);

    $first->assertOk();
    $second->assertOk();
    $second->assertJsonPath('data.meta.replayed', true);

    expect((float) $wallet->fresh()->balanceFloat)->toBe($balanceBefore + 100.00);
    expect(VoucherCollection::query()->where('voucher_id', $voucher->id)->count())->toBe(1);
});

it('confirms collectible voucher payment without authenticated user', function () {
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

    auth()->logout();

    $response = $this->postJson(route('api.x.v1.vouchers.payment-confirmations.store', [
        'code' => $voucher->code,
    ]), [
        'amount' => 100.00,
        'currency' => 'PHP',
        'status' => 'succeeded',
        'provider' => 'manual',
        'provider_reference' => 'REF-NO-AUTH-COLLECT-1',
        'provider_transaction_id' => 'TXN-NO-AUTH-COLLECT-1',
        'idempotency_key' => 'idem-no-auth-collect-1',
    ]);

    $response->assertOk();
    $response->assertJsonPath('success', true);
    $response->assertJsonPath('data.status', 'collected');

    expect((float) $wallet->fresh()->balanceFloat)->toBe($balanceBefore + 100.00);
});
