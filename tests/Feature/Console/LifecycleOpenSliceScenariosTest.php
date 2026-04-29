<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\VoucherFlowCapabilityResolverContract;
use LBHurtado\XChange\Enums\VoucherFlowType;
use LBHurtado\XChange\Models\DisbursementReconciliation;
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

it('runs the divisible open two slices scenario successfully', function () {
    $exitCode = Artisan::call('xchange:lifecycle:run', [
        'scenario' => 'divisible_open_two_slices',
        '--timeout' => 1,
        '--poll' => 1,
        '--accept-pending' => true,
        '--json' => true,
    ]);

    expect($exitCode)->toBe(0);

    $claims = VoucherClaim::query()
        ->latest('id')
        ->take(2)
        ->get()
        ->sortBy('claim_number')
        ->values();

    expect($claims)->toHaveCount(2);

    expect($claims[0]->claim_type)->toBe('withdraw');
    expect($claims[0]->requested_amount_minor)->toBe(10000);
    expect($claims[0]->disbursed_amount_minor)->toBe(10000);
    expect($claims[0]->remaining_balance_minor)->toBe(20000);

    expect($claims[1]->claim_type)->toBe('withdraw');
    expect($claims[1]->requested_amount_minor)->toBe(5000);
    expect($claims[1]->disbursed_amount_minor)->toBe(5000);
    expect($claims[1]->remaining_balance_minor)->toBe(15000);

    $reconciliations = DisbursementReconciliation::query()
        ->latest('id')
        ->take(2)
        ->get()
        ->values();

    expect($reconciliations)->toHaveCount(2);
});

it('runs the divisible open three slices enforced interval scenario successfully', function () {
    $exitCode = Artisan::call('xchange:lifecycle:run', [
        'scenario' => 'divisible_open_three_slices_enforced_interval',
        '--timeout' => 1,
        '--poll' => 1,
        '--accept-pending' => true,
        '--json' => true,
    ]);

    expect($exitCode)->toBe(0);

    $claims = VoucherClaim::query()
        ->latest('id')
        ->take(3)
        ->get()
        ->sortBy('claim_number')
        ->values();

    expect($claims)->toHaveCount(3);

    expect($claims[0]->claim_type)->toBe('withdraw');
    expect($claims[0]->requested_amount_minor)->toBe(7500);
    expect($claims[0]->disbursed_amount_minor)->toBe(7500);
    expect($claims[0]->remaining_balance_minor)->toBe(7500);

    expect($claims[1]->claim_type)->toBe('withdraw');
    expect($claims[1]->requested_amount_minor)->toBe(5000);
    expect($claims[1]->disbursed_amount_minor)->toBe(5000);
    expect($claims[1]->remaining_balance_minor)->toBe(2500);

    expect($claims[2]->claim_type)->toBe('withdraw');
    expect($claims[2]->requested_amount_minor)->toBe(2500);
    expect($claims[2]->disbursed_amount_minor)->toBe(2500);
    expect($claims[2]->remaining_balance_minor)->toBe(0);

    expect(data_get($claims[2]->meta, 'fully_claimed'))->toBeTrue();

    $reconciliations = DisbursementReconciliation::query()
        ->latest('id')
        ->take(3)
        ->get()
        ->values();

    expect($reconciliations)->toHaveCount(3);
});

it('issues open slice lifecycle scenarios as disbursable vouchers', function () {
    $exitCode = Artisan::call('xchange:lifecycle:run', [
        'scenario' => 'divisible_open_three_slices_enforced_interval',
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

    $capabilities = app(VoucherFlowCapabilityResolverContract::class)
        ->resolve($voucher);

    expect($capabilities->type)->toBe(VoucherFlowType::Disbursable);
    expect($capabilities->can_disburse)->toBeTrue();
});
