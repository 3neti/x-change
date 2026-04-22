<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Models\VoucherClaim;

beforeEach(function () {
    expect(Schema::hasTable('voucher_claims'))->toBeTrue();
    expect(Schema::hasTable('vouchers'))->toBeTrue();
});

it('persists a voucher claim ledger row', function () {
    $voucher = Voucher::query()->create([
        'code' => 'TEST-CLAIM-001',
        'metadata' => [
            'instructions' => [
                'cash' => [
                    'amount' => 300,
                    'currency' => 'PHP',
                    'slice_mode' => 'open',
                    'max_slices' => 3,
                    'min_withdrawal' => 50,
                ],
                'inputs' => ['fields' => []],
                'feedback' => [],
                'rider' => [],
            ],
        ],
        'state' => 'active',
    ]);

    $claim = VoucherClaim::query()->create([
        'voucher_id' => $voucher->id,
        'claim_number' => 1,
        'claim_type' => 'claim',
        'status' => 'succeeded',
        'requested_amount_minor' => 10000,
        'disbursed_amount_minor' => 10000,
        'remaining_balance_minor' => 20000,
        'currency' => 'PHP',
        'claimer_mobile' => '639171234567',
        'recipient_country' => 'PH',
        'bank_code' => 'GXCHPHM2XXX',
        'account_number_masked' => '*******1987',
        'idempotency_key' => 'claim-001',
        'reference' => 'REF-001',
        'attempted_at' => Carbon::parse('2026-04-22 10:00:00'),
        'completed_at' => Carbon::parse('2026-04-22 10:00:05'),
        'meta' => [
            'flow' => 'claim',
            'slice_mode' => 'open',
        ],
    ]);

    expect($claim->exists)->toBeTrue();
    expect($claim->voucher_id)->toBe($voucher->id);
    expect($claim->claim_number)->toBe(1);
    expect($claim->status)->toBe('succeeded');
    expect($claim->requested_amount_minor)->toBe(10000);
    expect($claim->disbursed_amount_minor)->toBe(10000);
    expect($claim->remaining_balance_minor)->toBe(20000);
});

it('belongs to a voucher', function () {
    $voucher = Voucher::query()->create([
        'code' => 'TEST-CLAIM-002',
        'metadata' => [
            'instructions' => [
                'cash' => [
                    'amount' => 100,
                    'currency' => 'PHP',
                ],
                'inputs' => ['fields' => []],
                'feedback' => [],
                'rider' => [],
            ],
        ],
        'state' => 'active',
    ]);

    $claim = VoucherClaim::query()->create([
        'voucher_id' => $voucher->id,
        'claim_number' => 1,
        'status' => 'pending',
        'currency' => 'PHP',
    ]);

    expect($claim->voucher)->not->toBeNull();
    expect($claim->voucher->is($voucher))->toBeTrue();
});

it('casts timestamps and meta correctly', function () {
    $voucher = Voucher::query()->create([
        'code' => 'TEST-CLAIM-003',
        'metadata' => [
            'instructions' => [
                'cash' => [
                    'amount' => 100,
                    'currency' => 'PHP',
                ],
                'inputs' => ['fields' => []],
                'feedback' => [],
                'rider' => [],
            ],
        ],
        'state' => 'active',
    ]);

    $claim = VoucherClaim::query()->create([
        'voucher_id' => $voucher->id,
        'claim_number' => 1,
        'status' => 'pending_review',
        'currency' => 'PHP',
        'attempted_at' => '2026-04-22 12:00:00',
        'completed_at' => '2026-04-22 12:00:10',
        'meta' => [
            'review_reason' => 'Low-confidence provider failure',
        ],
    ])->fresh();

    expect($claim->attempted_at)->toBeInstanceOf(Carbon::class);
    expect($claim->completed_at)->toBeInstanceOf(Carbon::class);
    expect($claim->meta)->toBeArray();
    expect($claim->meta['review_reason'])->toBe('Low-confidence provider failure');
});

it('exposes major-unit helpers from minor-unit storage', function () {
    $voucher = Voucher::query()->create([
        'code' => 'TEST-CLAIM-004',
        'metadata' => [
            'instructions' => [
                'cash' => [
                    'amount' => 300,
                    'currency' => 'PHP',
                ],
                'inputs' => ['fields' => []],
                'feedback' => [],
                'rider' => [],
            ],
        ],
        'state' => 'active',
    ]);

    $claim = VoucherClaim::query()->create([
        'voucher_id' => $voucher->id,
        'claim_number' => 2,
        'status' => 'succeeded',
        'requested_amount_minor' => 5000,
        'disbursed_amount_minor' => 5000,
        'remaining_balance_minor' => 25000,
        'currency' => 'PHP',
    ])->fresh();

    expect($claim->requested_amount)->toBe(50.0);
    expect($claim->disbursed_amount)->toBe(50.0);
    expect($claim->remaining_balance)->toBe(250.0);
});

it('exposes status helper methods', function () {
    $voucher = Voucher::query()->create([
        'code' => 'TEST-CLAIM-005',
        'metadata' => [
            'instructions' => [
                'cash' => [
                    'amount' => 100,
                    'currency' => 'PHP',
                ],
                'inputs' => ['fields' => []],
                'feedback' => [],
                'rider' => [],
            ],
        ],
        'state' => 'active',
    ]);

    $succeeded = VoucherClaim::query()->create([
        'voucher_id' => $voucher->id,
        'claim_number' => 1,
        'status' => 'succeeded',
        'currency' => 'PHP',
    ]);

    $failed = VoucherClaim::query()->create([
        'voucher_id' => $voucher->id,
        'claim_number' => 2,
        'status' => 'failed',
        'currency' => 'PHP',
    ]);

    $review = VoucherClaim::query()->create([
        'voucher_id' => $voucher->id,
        'claim_number' => 3,
        'status' => 'pending_review',
        'currency' => 'PHP',
    ]);

    expect($succeeded->isSuccessful())->toBeTrue();
    expect($failed->isFailed())->toBeTrue();
    expect($review->isPendingReview())->toBeTrue();
});

it('has many voucher claims', function () {
    $voucher = Voucher::query()->create([
        'code' => 'TEST-CLAIMS-REL-001',
        'metadata' => [
            'instructions' => [
                'cash' => [
                    'amount' => 300,
                    'currency' => 'PHP',
                ],
                'inputs' => ['fields' => []],
                'feedback' => [],
                'rider' => [],
            ],
        ],
        'state' => 'active',
    ]);

    VoucherClaim::query()->create([
        'voucher_id' => $voucher->id,
        'claim_number' => 1,
        'status' => 'succeeded',
        'currency' => 'PHP',
    ]);

    VoucherClaim::query()->create([
        'voucher_id' => $voucher->id,
        'claim_number' => 2,
        'status' => 'pending_review',
        'currency' => 'PHP',
    ]);

    $voucher->refresh();

    expect($voucher->claims)->toHaveCount(2);
    expect($voucher->claims->pluck('claim_number')->all())->toBe([1, 2]);
});
