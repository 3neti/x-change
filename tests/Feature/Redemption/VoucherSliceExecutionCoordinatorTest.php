<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use LBHurtado\Voucher\Enums\VoucherSliceSelectionPolicy;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Voucher\Services\VoucherSlicePlanFactory;
use LBHurtado\XChange\Enums\VoucherSliceExecutionStatus;
use LBHurtado\XChange\Exceptions\VoucherSliceExecutionConflict;
use LBHurtado\XChange\Models\DisbursementReconciliation;
use LBHurtado\XChange\Models\VoucherClaim;
use LBHurtado\XChange\Models\VoucherSliceExecution;
use LBHurtado\XChange\Models\VoucherSliceExecutionItem;
use LBHurtado\XChange\Models\VoucherSliceExecutionOutbox;
use LBHurtado\XChange\Services\Claim\ClaimExperienceCompiler;
use LBHurtado\XChange\Services\Slices\VoucherSliceExecutionCoordinator;
use LBHurtado\XChange\Services\Slices\VoucherSlicePlanProjection;
use LBHurtado\XJournal\Models\ExecutionJournalEntry;

function voucherWithSlicePlan(array $plan): Voucher
{
    return Voucher::query()->create([
        'code' => 'SL'.fake()->bothify('??##'),
        'metadata' => [
            'instructions' => [
                'slice_plan' => $plan,
            ],
        ],
    ]);
}

it('reserves canonical equal slices with stable replay identity', function () {
    $plan = app(VoucherSlicePlanFactory::class)->equal(10_000, 'PHP', 2);
    $voucher = voucherWithSlicePlan($plan->canonicalArray());
    $coordinator = app(VoucherSliceExecutionCoordinator::class);
    $payload = [
        'mobile' => '09170000001',
        '_meta' => ['idempotency_key' => 'slice-claim-1'],
    ];

    $first = $coordinator->reserve($voucher, $payload);
    $replay = $coordinator->reserve($voucher, $payload);

    expect($first)->not->toBeNull()
        ->and($first?->replayed)->toBeFalse()
        ->and($replay?->replayed)->toBeTrue()
        ->and($replay?->execution->getKey())->toBe($first?->execution->getKey())
        ->and(data_get($first?->payload, '_slice_execution.provider_operation_reference'))
        ->toBe($first?->execution->provider_operation_reference)
        ->and(VoucherSliceExecution::query()->count())->toBe(1)
        ->and(VoucherSliceExecutionItem::query()->value('slice_id'))->toBe('slice_1')
        ->and(VoucherSliceExecutionOutbox::query()->where('event_type', 'voucher.slice.reserved')->value('status'))->toBe('delivered')
        ->and(ExecutionJournalEntry::query()->where('event_type', 'voucher.slice.reserved')->count())->toBe(1);

    $journalPayload = json_encode(
        ExecutionJournalEntry::query()->where('event_type', 'voucher.slice.reserved')->firstOrFail()->toArray(),
        JSON_THROW_ON_ERROR,
    );

    expect($journalPayload)
        ->not->toContain($voucher->code)
        ->not->toContain('09170000001')
        ->not->toContain((string) $first?->execution->provider_operation_reference);
});

it('rejects changed facts for a claimed idempotency key', function () {
    $plan = app(VoucherSlicePlanFactory::class)->equal(10_000, 'PHP', 2);
    $voucher = voucherWithSlicePlan($plan->canonicalArray());
    $coordinator = app(VoucherSliceExecutionCoordinator::class);

    $coordinator->reserve($voucher, [
        'mobile' => '09170000001',
        '_meta' => ['idempotency_key' => 'slice-claim-conflict'],
    ]);

    $coordinator->reserve($voucher, [
        'mobile' => '09170000002',
        '_meta' => ['idempotency_key' => 'slice-claim-conflict'],
    ]);
})->throws(VoucherSliceExecutionConflict::class, 'different claim facts');

it('reopens an indeterminate reservation only when no provider intent exists', function () {
    $plan = app(VoucherSlicePlanFactory::class)->equal(10_000, 'PHP', 2);
    $voucher = voucherWithSlicePlan($plan->canonicalArray());
    $coordinator = app(VoucherSliceExecutionCoordinator::class);
    $payload = [
        'mobile' => '09170000001',
        '_meta' => ['idempotency_key' => 'pre-provider-retry'],
    ];
    $reservation = $coordinator->reserve($voucher, $payload);

    $coordinator->begin($reservation->execution);
    $coordinator->indeterminate($reservation->execution);

    $replay = $coordinator->reserve($voucher, $payload);

    expect($replay?->replayed)->toBeTrue()
        ->and($replay?->execution->status)->toBe(VoucherSliceExecutionStatus::Reserved)
        ->and(VoucherSliceExecutionOutbox::query()
            ->where('event_type', 'voucher.slice.execution_reopened')
            ->count())->toBe(1);
});

it('reopens a pre-provider failure for the same facts under a fresh browser flow key', function () {
    $plan = app(VoucherSlicePlanFactory::class)->equal(10_000, 'PHP', 2);
    $voucher = voucherWithSlicePlan($plan->canonicalArray());
    $coordinator = app(VoucherSliceExecutionCoordinator::class);
    $reservation = $coordinator->reserve($voucher, [
        'mobile' => '09170000001',
        '_meta' => ['idempotency_key' => 'failed-browser-flow'],
    ]);

    $coordinator->failBeforeProvider($reservation->execution);
    $replay = $coordinator->reserve($voucher, [
        'mobile' => '09170000001',
        '_meta' => ['idempotency_key' => 'fresh-browser-flow'],
    ]);

    expect($replay?->replayed)->toBeTrue()
        ->and($replay?->execution->getKey())->toBe($reservation->execution->getKey())
        ->and($replay?->execution->status)->toBe(VoucherSliceExecutionStatus::Reserved)
        ->and($replay?->execution->items()->value('status'))->toBe('reserved');
});

it('keeps an indeterminate reservation locked when provider intent exists', function () {
    $plan = app(VoucherSlicePlanFactory::class)->equal(10_000, 'PHP', 2);
    $voucher = voucherWithSlicePlan($plan->canonicalArray());
    $coordinator = app(VoucherSliceExecutionCoordinator::class);
    $payload = [
        'mobile' => '09170000001',
        '_meta' => ['idempotency_key' => 'post-provider-retry'],
    ];
    $reservation = $coordinator->reserve($voucher, $payload);
    $coordinator->begin($reservation->execution);
    $coordinator->indeterminate($reservation->execution);
    DisbursementReconciliation::query()->create([
        'voucher_id' => $voucher->getKey(),
        'voucher_code' => $voucher->code,
        'claim_type' => 'withdraw',
        'provider' => 'unknown',
        'provider_reference' => $reservation->execution->provider_operation_reference,
        'amount' => 50,
        'currency' => 'PHP',
        'status' => 'intent',
        'internal_status' => 'intent',
    ]);

    $replay = $coordinator->reserve($voucher, $payload);

    expect($replay?->execution->status)->toBe(VoucherSliceExecutionStatus::Indeterminate)
        ->and(VoucherSliceExecutionOutbox::query()
            ->where('event_type', 'voucher.slice.execution_reopened')
            ->count())->toBe(0);
});

it('makes a scheduled slice unavailable as soon as it is reserved', function () {
    $plan = app(VoucherSlicePlanFactory::class)->scheduled(
        totalMinor: 10_000,
        currency: 'PHP',
        slices: [
            ['id' => 'fare_one', 'label' => 'Morning fare', 'amount_minor' => 5_000],
            ['id' => 'fare_two', 'label' => 'Evening fare', 'amount_minor' => 5_000],
        ],
        selection: VoucherSliceSelectionPolicy::One,
    );
    $voucher = voucherWithSlicePlan($plan->canonicalArray());
    $coordinator = app(VoucherSliceExecutionCoordinator::class);

    $coordinator->reserve($voucher, [
        'slice_ids' => ['fare_one'],
        '_meta' => ['idempotency_key' => 'scheduled-fare-1'],
    ]);

    expect(fn () => $coordinator->reserve($voucher, [
        'slice_ids' => ['fare_one'],
        '_meta' => ['idempotency_key' => 'scheduled-fare-2'],
    ]))->toThrow(ValidationException::class, 'unavailable');

    $projection = app(VoucherSlicePlanProjection::class)->forVoucher($voucher);

    expect(data_get($projection, 'reserved_minor'))->toBe(5_000)
        ->and(data_get($projection, 'available_minor'))->toBe(5_000)
        ->and(data_get($projection, 'rows.0.status_label'))->toBe('In progress')
        ->and(data_get($projection, 'rows.1.status_label'))->toBe('Available');
});

it('moves reserved evidence to consumed exactly once after settlement', function () {
    $plan = app(VoucherSlicePlanFactory::class)->equal(10_000, 'PHP', 2);
    $voucher = voucherWithSlicePlan($plan->canonicalArray());
    $coordinator = app(VoucherSliceExecutionCoordinator::class);
    $reservation = $coordinator->reserve($voucher, [
        '_meta' => ['idempotency_key' => 'settled-slice-1'],
    ]);
    $claim = VoucherClaim::query()->create([
        'voucher_id' => $voucher->getKey(),
        'claim_number' => $reservation->execution->claim_number,
        'claim_type' => 'withdraw',
        'status' => 'succeeded',
        'currency' => 'PHP',
        'attempted_at' => now(),
    ]);

    $coordinator->begin($reservation->execution);
    $coordinator->succeed($reservation->execution, $claim);
    $coordinator->succeed($reservation->execution, $claim);

    $projection = app(VoucherSlicePlanProjection::class)->forVoucher($voucher);

    expect($reservation->execution->fresh()->status)->toBe(VoucherSliceExecutionStatus::Succeeded)
        ->and($reservation->execution->items()->value('status'))->toBe('consumed')
        ->and(VoucherSliceExecutionOutbox::query()->where('event_type', 'voucher.slice.consumed')->count())->toBe(1)
        ->and(data_get($projection, 'consumed_minor'))->toBe(5_000)
        ->and(data_get($projection, 'rows.0.claimed_at'))->toBeString();
});

it('converts flexible slice amounts to exact minor units', function () {
    $plan = app(VoucherSlicePlanFactory::class)->flexible(
        totalMinor: 10_000,
        currency: 'PHP',
        maxSlices: 10,
        minAmountMinor: 1,
    );
    $voucher = voucherWithSlicePlan($plan->canonicalArray());
    $coordinator = app(VoucherSliceExecutionCoordinator::class);

    $reservation = $coordinator->reserve($voucher, [
        'amount' => '30.01',
        '_meta' => ['idempotency_key' => 'flexible-exact-money'],
    ]);

    expect($reservation?->execution->amount_minor)->toBe(3_001)
        ->and($reservation?->execution->items()->value('amount_minor'))->toBe(3_001);

    $coordinator->reserve($voucher, [
        'amount' => '30.02',
        '_meta' => ['idempotency_key' => 'flexible-exact-money'],
    ]);
})->throws(VoucherSliceExecutionConflict::class, 'different claim facts');

it('shows claim capacity and requires the final flexible claim to use the remaining balance', function () {
    $plan = app(VoucherSlicePlanFactory::class)->flexible(
        totalMinor: 10_000,
        currency: 'PHP',
        maxSlices: 2,
        minAmountMinor: 2_500,
    );
    $voucher = voucherWithSlicePlan($plan->canonicalArray());
    $coordinator = app(VoucherSliceExecutionCoordinator::class);

    $coordinator->reserve($voucher, [
        'amount' => '25.00',
        '_meta' => ['idempotency_key' => 'flexible-first-claim'],
    ]);

    $projection = app(VoucherSlicePlanProjection::class)->forVoucher($voucher);
    $experience = app(ClaimExperienceCompiler::class)->compile($voucher)->toArray();
    $amount = collect(collect($experience['phases'])->firstWhere('key', 'form_flow')['fields'])
        ->firstWhere('key', 'amount');

    expect(data_get($projection, 'claims_used'))->toBe(1)
        ->and(data_get($projection, 'claims_remaining'))->toBe(1)
        ->and(data_get($projection, 'is_final_claim'))->toBeTrue()
        ->and(data_get($projection, 'rows.1.max_slices'))->toBe(2)
        ->and(data_get($projection, 'rows.1.min_amount_minor'))->toBe(2_500)
        ->and(data_get($projection, 'rows.1.claims_remaining'))->toBe(1)
        ->and(data_get($projection, 'rows.1.is_final_claim'))->toBeTrue()
        ->and($amount['label'])->toBe('Final claim · Claim 2 of 2')
        ->and($amount['description'])->toBe('Claim the full remaining balance of ₱75.00.')
        ->and($amount['min'])->toBe(75)
        ->and($amount['max'])->toBe(75);

    expect(fn () => $coordinator->reserve($voucher, [
        'amount' => '50.00',
        '_meta' => ['idempotency_key' => 'flexible-partial-final-claim'],
    ]))->toThrow(ValidationException::class, 'final claim must use the full remaining balance');

    $final = $coordinator->reserve($voucher, [
        'amount' => '75.00',
        '_meta' => ['idempotency_key' => 'flexible-complete-final-claim'],
    ]);

    expect($final?->execution->amount_minor)->toBe(7_500);
});

it('prevents a flexible claim from leaving an unusable residual balance', function () {
    $plan = app(VoucherSlicePlanFactory::class)->flexible(
        totalMinor: 10_000,
        currency: 'PHP',
        maxSlices: 3,
        minAmountMinor: 2_500,
    );
    $voucher = voucherWithSlicePlan($plan->canonicalArray());
    $coordinator = app(VoucherSliceExecutionCoordinator::class);

    expect(fn () => $coordinator->reserve($voucher, [
        'amount' => '80.00',
        '_meta' => ['idempotency_key' => 'flexible-unusable-residual'],
    ]))->toThrow(ValidationException::class, 'no unusable balance is left behind');

    $full = $coordinator->reserve($voucher, [
        'amount' => '100.00',
        '_meta' => ['idempotency_key' => 'flexible-full-early-claim'],
    ]);

    expect($full?->execution->amount_minor)->toBe(10_000);
});
