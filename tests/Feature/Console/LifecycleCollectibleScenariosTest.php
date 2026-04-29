<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\VoucherFlowCapabilityResolverContract;
use LBHurtado\XChange\Enums\VoucherFlowType;
use LBHurtado\XChange\Models\VoucherClaim;
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
