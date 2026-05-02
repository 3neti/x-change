<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\VoucherFlowCapabilityResolverContract;
use LBHurtado\XChange\Enums\VoucherFlowType;
use LBHurtado\XChange\Models\VoucherClaim;
use LBHurtado\XChange\Models\VoucherCollection;
use LBHurtado\XChange\Tests\Fakes\User as FakeLifecycleUser;

beforeEach(function () {
    config([
        'x-change.lifecycle.defaults.user_model' => FakeLifecycleUser::class,
        'x-change.withdrawal.open_slice_min_interval_seconds' => 0,
    ]);

    expect(FakeLifecycleUser::class)->not->toBe('');
    expect(class_exists(FakeLifecycleUser::class))->toBeTrue();
    expect(config('x-change.lifecycle.defaults.user_model'))->toBe(FakeLifecycleUser::class);
    expect(class_exists((string) config('x-change.lifecycle.defaults.user_model')))->toBeTrue();

    Artisan::call('xchange:lifecycle:prepare', [
        '--seed' => true,
    ]);
});

it('runs collectible basic payment scenario without withdrawal claims', function () {
    $exitCode = Artisan::call('xchange:lifecycle:run', [
        'scenario' => 'collectible_basic_payment',
        '--timeout' => 1,
        '--poll' => 1,
        '--accept-pending' => true,
        '--json' => true,
    ]);

    expect($exitCode)->toBe(0);

    $voucher = Voucher::query()
        ->latest('id')
        ->first();

    expect($voucher)->not->toBeNull();

    $claims = VoucherClaim::query()
        ->where('voucher_id', $voucher->id)
        ->get();

    expect($claims)->toBeEmpty();
});

it('issues collectible scenario vouchers with collectible capabilities', function () {
    $exitCode = Artisan::call('xchange:lifecycle:run', [
        'scenario' => 'collectible_basic_payment',
        '--timeout' => 1,
        '--poll' => 1,
        '--accept-pending' => true,
        '--json' => true,
    ]);

    expect($exitCode)->toBe(0);

    $voucher = Voucher::query()
        ->latest('id')
        ->first();

    expect($voucher)->not->toBeNull();
    $voucher = $voucher->fresh();

    expect(data_get($voucher->metadata, 'instructions.metadata.flow_type'))->toBe('collectible');

    $capabilities = app(VoucherFlowCapabilityResolverContract::class)->resolve($voucher);

    expect($capabilities->type)->toBe(VoucherFlowType::Collectible);
    expect($capabilities->can_collect)->toBeTrue();
    expect($capabilities->can_disburse)->toBeFalse();

    expect(
        VoucherClaim::query()->where('voucher_id', $voucher->id)->exists()
    )->toBeFalse();
});

it('resolves collectible flow type from instructions metadata before inference', function () {
    $voucher = issueVoucher(validVoucherInstructions(
        amount: 100,
        settlementRail: 'INSTAPAY',
        overrides: [
            'inputs' => [
                'fields' => [],
            ],
            'metadata' => [
                'flow_type' => 'collectible',
            ],
        ],
    ));

    $voucher->refresh();

    expect(data_get($voucher->metadata, 'instructions.metadata.flow_type'))
        ->toBe('collectible');

    $capabilities = app(VoucherFlowCapabilityResolverContract::class)
        ->resolve($voucher);

    expect($capabilities->type)->toBe(VoucherFlowType::Collectible)
        ->and($capabilities->can_collect)->toBeTrue()
        ->and($capabilities->can_disburse)->toBeFalse()
        ->and($capabilities->can_withdraw ?? false)->toBeFalse();
});

it('runs collectible basic payment end-to-end', function () {
    config()->set('x-change.payment_qr.renderer', 'json');

    $issuer = FakeLifecycleUser::query()
        ->where('email', 'system@example.test')
        ->first();

    expect($issuer)->not->toBeNull();

    $wallet = $issuer->wallet;
    $balanceBeforeIssuance = (float) $wallet->balanceFloat;

    $exitCode = Artisan::call('xchange:lifecycle:run', [
        'scenario' => 'collectible_basic_payment',
        '--timeout' => 1,
        '--poll' => 1,
        '--accept-pending' => true,
        '--json' => true,
    ]);

    expect($exitCode)->toBe(0);

    $voucher = Voucher::query()
        ->latest('id')
        ->first();

    expect($voucher)->not->toBeNull();

    $voucher = $voucher->fresh();
    $balanceAfterIssuance = (float) $wallet->fresh()->balanceFloat;

    expect(data_get($voucher->metadata, 'instructions.metadata.flow_type'))->toBe('collectible');

    $capabilities = app(VoucherFlowCapabilityResolverContract::class)->resolve($voucher);

    expect($capabilities->type)->toBe(VoucherFlowType::Collectible)
        ->and($capabilities->can_collect)->toBeTrue()
        ->and($capabilities->can_disburse)->toBeFalse();

    expect(
        VoucherClaim::query()->where('voucher_id', $voucher->id)->exists()
    )->toBeFalse();

    $qrResponse = $this->getJson(route('api.x.v1.vouchers.payment-qr.show', [
        'code' => $voucher->code,
    ]));

    $qrResponse->assertOk();
    $qrResponse->assertJsonPath('success', true);
    $qrResponse->assertJsonPath('data.voucher_code', $voucher->code);
    $qrResponse->assertJsonPath('data.payload.flow_type', 'collectible');
    $qrResponse->assertJsonPath('data.payload.route_key', 'pay');

    $targetAmount = (float) data_get($voucher->metadata, 'instructions.target_amount', 100.00);
    $debitedAtIssuance = $balanceBeforeIssuance - $balanceAfterIssuance;

    expect($debitedAtIssuance)->toBeLessThan($targetAmount);

    $this->actingAs($issuer);

    $paymentResponse = $this->postJson(route('api.x.v1.vouchers.payment-confirmations.store', [
        'code' => $voucher->code,
    ]), [
        'amount' => $targetAmount,
        'currency' => 'PHP',
        'status' => 'succeeded',
        'provider' => 'manual',
        'provider_reference' => 'REF-LIFECYCLE-COLLECTIBLE-E2E',
        'provider_transaction_id' => 'TXN-LIFECYCLE-COLLECTIBLE-E2E',
        'idempotency_key' => 'idem-lifecycle-collectible-e2e',
        'payer' => [
            'name' => 'Juan Dela Cruz',
            'mobile' => '09171234567',
        ],
    ]);

    $paymentResponse->assertOk();
    $paymentResponse->assertJsonPath('success', true);
    $paymentResponse->assertJsonPath('data.status', 'collected');
    $paymentResponse->assertJsonPath('data.voucher_code', $voucher->code);

    expect((float) $wallet->fresh()->balanceFloat)
        ->toBe($balanceAfterIssuance + $targetAmount);

    $collection = VoucherCollection::query()
        ->where('voucher_id', $voucher->id)
        ->latest('id')
        ->first();

    expect($collection)->not->toBeNull()
        ->and($collection->status)->toBe('collected')
        ->and($collection->requested_amount_minor)->toBe((int) round($targetAmount * 100))
        ->and($collection->collected_amount_minor)->toBe((int) round($targetAmount * 100))
        ->and($collection->provider_reference)->toBe('REF-LIFECYCLE-COLLECTIBLE-E2E')
        ->and($collection->wallet_transaction_id)->not->toBeNull();

    $voucher = $voucher->fresh();

    expect(data_get($voucher->metadata, 'collection_progress'))->toBeArray()
        ->and(data_get($voucher->metadata, 'collection_progress.collected_total_minor'))
        ->toBe((int) round($targetAmount * 100))
        ->and(data_get($voucher->metadata, 'collection_progress.remaining_to_collect_minor'))
        ->toBe(0)
        ->and(data_get($voucher->metadata, 'collection_progress.is_fully_collected'))
        ->toBeTrue();
});
