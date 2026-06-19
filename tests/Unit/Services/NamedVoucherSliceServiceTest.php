<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Models\VoucherClaim;
use LBHurtado\XChange\Services\NamedVoucherSliceService;

function namedSliceVoucher(array $slices): Voucher
{
    return Voucher::query()->create([
        'code' => 'NSLICE'.fake()->numerify('####'),
        'metadata' => [
            'instructions' => [
                'cash' => [
                    'amount' => array_sum(array_map(
                        static fn (array $slice): float => (float) $slice['amount'],
                        $slices
                    )),
                    'currency' => 'PHP',
                    'slice_mode' => 'open',
                ],
                'metadata' => [
                    'slices' => $slices,
                ],
            ],
        ],
    ]);
}

it('normalizes named slices into open-slice compatible issuance metadata', function () {
    $payload = app(NamedVoucherSliceService::class)->normalizeIssuancePayload([
        'cash' => [
            'amount' => 10000,
            'currency' => 'PHP',
        ],
        'metadata' => [
            'slices' => [
                [
                    'amount' => 6000,
                    'description' => 'Buy Product 1',
                    'tag' => 'product',
                ],
                [
                    'amount' => 4000,
                    'description' => 'Pay for Service 1',
                    'tag' => 'service',
                ],
            ],
        ],
    ]);

    expect(data_get($payload, 'cash.slice_mode'))->toBe('open')
        ->and(data_get($payload, 'cash.max_slices'))->toBe(2)
        ->and(data_get($payload, 'cash.min_withdrawal'))->toBe(4000)
        ->and(data_get($payload, 'metadata.custom.named_slice_policy.mode'))->toBe('named')
        ->and(data_get($payload, 'metadata.custom.named_slices.0.id'))->toBe('slice_1')
        ->and(data_get($payload, 'metadata.custom.named_slices.0.description'))->toBe('Buy Product 1');
});

it('rejects named slices that do not add up to the voucher amount', function () {
    app(NamedVoucherSliceService::class)->normalizeIssuancePayload([
        'cash' => [
            'amount' => 10000,
            'currency' => 'PHP',
        ],
        'metadata' => [
            'slices' => [
                ['amount' => 6000],
                ['amount' => 3000],
            ],
        ],
    ]);
})->throws(ValidationException::class, 'Named slice amounts must equal the Pay Code amount.');

it('derives claim amount from selected named slices', function () {
    $voucher = namedSliceVoucher([
        [
            'id' => 'slice_1',
            'amount' => 6000,
            'description' => 'Buy Product 1',
        ],
        [
            'id' => 'slice_2',
            'amount' => 4000,
            'description' => 'Pay for Service 1',
        ],
    ]);

    $payload = app(NamedVoucherSliceService::class)->enrichClaimPayload($voucher, [
        'amount' => 1,
        'slice_ids' => ['slice_1', 'slice_2'],
    ]);

    expect($payload['amount'])->toBe(10000.0)
        ->and($payload['_named_slices']['selected'])->toHaveCount(2);
});

it('blocks already claimed named slices', function () {
    $voucher = namedSliceVoucher([
        [
            'id' => 'slice_1',
            'amount' => 6000,
            'description' => 'Buy Product 1',
        ],
    ]);

    VoucherClaim::query()->create([
        'voucher_id' => $voucher->getKey(),
        'claim_number' => 1,
        'claim_type' => 'withdraw',
        'status' => 'succeeded',
        'currency' => 'PHP',
        'attempted_at' => now(),
        'meta' => [
            'named_slices' => [
                'selected_ids' => ['slice_1'],
            ],
        ],
    ]);

    app(NamedVoucherSliceService::class)->enrichClaimPayload($voucher->fresh(), [
        'slice_ids' => ['slice_1'],
    ]);
})->throws(ValidationException::class, 'Already claimed.');

it('detects remaining unclaimed named slices after a partial claim', function () {
    $voucher = namedSliceVoucher([
        [
            'id' => 'slice_1',
            'amount' => 80,
            'description' => 'Buy coffee',
        ],
        [
            'id' => 'slice_2',
            'amount' => 75,
            'description' => 'Buy doughnut',
        ],
    ]);

    VoucherClaim::query()->create([
        'voucher_id' => $voucher->getKey(),
        'claim_number' => 1,
        'claim_type' => 'withdraw',
        'status' => 'withdrawn',
        'currency' => 'PHP',
        'attempted_at' => now(),
        'meta' => [
            'fully_claimed' => false,
            'named_slices' => [
                'selected_ids' => ['slice_1'],
            ],
        ],
    ]);

    $service = app(NamedVoucherSliceService::class);

    expect($service->hasUnclaimedSlices($voucher->fresh()))->toBeTrue()
        ->and($service->allSlicesClaimed($voucher->fresh()))->toBeFalse();
});

it('detects when all named slices are claimed', function () {
    $voucher = namedSliceVoucher([
        [
            'id' => 'slice_1',
            'amount' => 80,
            'description' => 'Buy coffee',
        ],
        [
            'id' => 'slice_2',
            'amount' => 75,
            'description' => 'Buy doughnut',
        ],
    ]);

    VoucherClaim::query()->create([
        'voucher_id' => $voucher->getKey(),
        'claim_number' => 1,
        'claim_type' => 'withdraw',
        'status' => 'withdrawn',
        'currency' => 'PHP',
        'attempted_at' => now(),
        'meta' => [
            'named_slices' => [
                'selected_ids' => ['slice_1', 'slice_2'],
            ],
        ],
    ]);

    $service = app(NamedVoucherSliceService::class);

    expect($service->hasUnclaimedSlices($voucher->fresh()))->toBeFalse()
        ->and($service->allSlicesClaimed($voucher->fresh()))->toBeTrue();
});
