<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LBHurtado\Voucher\Enums\VoucherSliceSelectionPolicy;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Voucher\Services\VoucherSlicePlanFactory;
use LBHurtado\XChange\Models\VoucherSliceExecution;
use LBHurtado\XChange\Models\VoucherSliceExecutionItem;
use LBHurtado\XChange\Services\NamedVoucherSliceService;
use LBHurtado\XChange\Services\Slices\VoucherSliceExecutionCoordinator;

function namedSliceVoucher(array $slices): Voucher
{
    $totalMinor = (int) round(array_sum(array_map(
        static fn (array $slice): float => (float) $slice['amount'],
        $slices,
    )) * 100);
    $plan = app(VoucherSlicePlanFactory::class)->scheduled(
        totalMinor: $totalMinor,
        currency: 'PHP',
        slices: array_map(static fn (array $slice): array => [
            'id' => $slice['id'] ?? null,
            'label' => $slice['description'] ?? null,
            'amount_minor' => (int) round(((float) $slice['amount']) * 100),
            'claim_on' => $slice['claim_on'] ?? null,
            'claim_by' => $slice['claim_by'] ?? null,
        ], $slices),
        selection: VoucherSliceSelectionPolicy::OneOrMany,
    );

    return Voucher::query()->create([
        'code' => 'NSLICE'.fake()->numerify('####'),
        'metadata' => [
            'instructions' => [
                'slice_plan' => $plan->canonicalArray(),
            ],
        ],
    ]);
}

function consumeNamedSlices(Voucher $voucher, array $sliceIds): void
{
    $reference = (string) Str::ulid();
    $execution = VoucherSliceExecution::query()->create([
        'reference' => $reference,
        'voucher_id' => $voucher->getKey(),
        'plan_fingerprint' => app(VoucherSliceExecutionCoordinator::class)->plan($voucher)->hash(),
        'idempotency_key_hash' => hash('sha256', $reference),
        'request_fingerprint' => hash('sha256', 'request-'.$reference),
        'provider_operation_reference' => 'slice-'.$reference,
        'claim_number' => 1,
        'status' => 'succeeded',
        'amount_minor' => 1,
        'currency' => 'PHP',
        'reserved_at' => now(),
        'settled_at' => now(),
    ]);

    foreach ($sliceIds as $index => $sliceId) {
        VoucherSliceExecutionItem::query()->create([
            'execution_id' => $execution->getKey(),
            'voucher_id' => $voucher->getKey(),
            'slice_id' => $sliceId,
            'label' => 'Slice '.($index + 1),
            'sequence' => $index + 1,
            'amount_minor' => 1,
            'status' => 'consumed',
            'reserved_at' => now(),
            'consumed_at' => now(),
        ]);
    }
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

    expect(data_get($payload, 'slice_plan.mode'))->toBe('scheduled')
        ->and(data_get($payload, 'slice_plan.selection'))->toBe('one_or_many')
        ->and(data_get($payload, 'slice_plan.slices.0.id'))->toBe('slice_1')
        ->and(data_get($payload, 'slice_plan.slices.0.label'))->toBe('Buy Product 1')
        ->and(data_get($payload, 'cash.slice_mode'))->toBeNull()
        ->and(data_get($payload, 'metadata.slices'))->toBeNull();
});

it('migrates transient fixed and open slice payloads into the canonical plan', function (array $payload, string $mode) {
    $normalized = app(NamedVoucherSliceService::class)->normalizeIssuancePayload($payload);

    expect(data_get($normalized, 'slice_plan.mode'))->toBe($mode)
        ->and(data_get($normalized, 'cash.slice_mode'))->toBeNull()
        ->and(data_get($normalized, 'metadata.slice_policy'))->toBeNull();
})->with([
    'fixed slices' => [[
        'cash' => [
            'amount' => 100,
            'currency' => 'PHP',
            'slice_mode' => 'fixed',
            'slices' => 4,
        ],
        'metadata' => [
            'custom' => [
                'cockpit' => [
                    'slice_plan' => [
                        'mode' => 'fixed',
                        'rows' => [
                            ['id' => 'slice_1', 'amount' => 25],
                            ['id' => 'slice_2', 'amount' => 25],
                            ['id' => 'slice_3', 'amount' => 25],
                            ['id' => 'slice_4', 'amount' => 25],
                        ],
                    ],
                ],
            ],
        ],
    ], 'equal'],
    'open slices' => [[
        'cash' => [
            'amount' => 100,
            'currency' => 'PHP',
            'slice_mode' => 'open',
            'max_slices' => 3,
            'min_withdrawal' => 30,
        ],
        'metadata' => [
            'slice_policy' => [
                'mode' => 'open',
                'selection' => 'operator',
                'enforced' => false,
            ],
            'custom' => [
                'cockpit' => [
                    'slice_plan' => [
                        'mode' => 'open',
                        'rows' => [
                            ['id' => 'slice_1', 'amount' => 100],
                        ],
                    ],
                ],
            ],
        ],
    ], 'flexible'],
]);

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

it('rejects named slices below the configured effective minimum withdrawal', function () {
    config()->set('x-change.minimum_withdrawal.default', 25.00);

    app(NamedVoucherSliceService::class)->normalizeIssuancePayload([
        'provider' => 'manual',
        'cash' => [
            'amount' => 100,
            'currency' => 'PHP',
        ],
        'metadata' => [
            'slices' => [
                ['amount' => 80],
                ['amount' => 20],
            ],
        ],
    ]);
})->throws(ValidationException::class, 'Named slice amount must be at least PHP 25.00.');

it('rejects a visual slice plan when an outdated cockpit session omits the executable plan', function () {
    app(NamedVoucherSliceService::class)->normalizeIssuancePayload([
        'cash' => [
            'amount' => 75,
            'currency' => 'PHP',
        ],
        'metadata' => [
            'custom' => [
                'cockpit' => [
                    'slice_plan' => [
                        'schema' => 'x-change.cockpit.slice-plan.v1',
                        'mode' => 'fixed',
                        'rows' => [
                            ['id' => 'slice_1', 'amount' => 25, 'description' => 'Slice 1'],
                            ['id' => 'slice_2', 'amount' => 25, 'description' => 'Slice 2'],
                            ['id' => 'slice_3', 'amount' => 25, 'description' => 'Slice 3'],
                        ],
                    ],
                ],
            ],
        ],
    ]);
})->throws(
    ValidationException::class,
    'This slice configuration came from an outdated Quick Generate session. Refresh the page and configure the slices again.',
);

it('rejects disagreement between the cockpit value-use mode and executable plan', function () {
    $plan = app(VoucherSlicePlanFactory::class)->equal(7_500, 'PHP', 3);

    app(NamedVoucherSliceService::class)->normalizeIssuancePayload([
        'cash' => [
            'amount' => 75,
            'currency' => 'PHP',
        ],
        'slice_plan' => $plan->canonicalArray(),
        'metadata' => [
            'custom' => [
                'cockpit' => [
                    'slice_plan' => [
                        'schema' => 'x-change.cockpit.slice-plan.v1',
                        'mode' => 'open',
                    ],
                ],
            ],
        ],
    ]);
})->throws(
    ValidationException::class,
    'The executable slice plan does not match the selected Quick Generate value-use mode.',
);

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
        [
            'id' => 'slice_2',
            'amount' => 1,
            'description' => 'Buy Product 2',
        ],
    ]);

    consumeNamedSlices($voucher, ['slice_1']);

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

    consumeNamedSlices($voucher, ['slice_1']);

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

    consumeNamedSlices($voucher, ['slice_1', 'slice_2']);

    $service = app(NamedVoucherSliceService::class);

    expect($service->hasUnclaimedSlices($voucher->fresh()))->toBeFalse()
        ->and($service->allSlicesClaimed($voucher->fresh()))->toBeTrue();
});
