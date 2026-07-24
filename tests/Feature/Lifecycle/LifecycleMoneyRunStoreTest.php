<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use LBHurtado\XChange\Lifecycle\Scenarios\LifecycleMoneyRunStore;
use LBHurtado\XChange\Models\LifecycleMoneyRun;

it('persists a stable money run without retaining the operator reference', function () {
    $issuer = actingAsTestUser();
    $store = app(LifecycleMoneyRunStore::class);

    $first = $store->begin(
        'treasury_live_basic_cash',
        'operator-run-2026-07-24-001',
        $issuer,
        'netbank',
        1250,
        'PHP',
    );
    $replayed = $store->begin(
        'treasury_live_basic_cash',
        'operator-run-2026-07-24-001',
        $issuer,
        'netbank',
        1250,
        'PHP',
    );

    expect(Schema::hasTable('x_change_lifecycle_money_runs'))->toBeTrue()
        ->and($replayed->is($first))->toBeTrue()
        ->and(LifecycleMoneyRun::query()->count())->toBe(1)
        ->and($first->getRawOriginal('run_reference_hash'))
        ->not->toBe('operator-run-2026-07-24-001');
});

it('rejects reuse of a run reference for different money movement parameters', function () {
    $issuer = actingAsTestUser();
    $store = app(LifecycleMoneyRunStore::class);

    $store->begin(
        'treasury_live_basic_cash',
        'operator-run-001',
        $issuer,
        'netbank',
        1250,
        'PHP',
    );

    expect(fn () => $store->begin(
        'treasury_live_basic_cash',
        'operator-run-001',
        $issuer,
        'netbank',
        2500,
        'PHP',
    ))->toThrow(
        RuntimeException::class,
        'already bound to different money-movement parameters',
    );
});

it('records the issued Pay Code and a durable completed result', function () {
    $issuer = actingAsTestUser();
    $store = app(LifecycleMoneyRunStore::class);
    $run = $store->begin(
        'treasury_live_basic_cash',
        'operator-run-002',
        $issuer,
        'netbank',
        1250,
        'PHP',
    );

    $store->attachVoucher($run, 42);
    $completed = $store->complete($run->refresh(), [
        'success' => true,
        'provider_transfer_succeeded' => true,
    ]);

    expect($completed->status)->toBe('completed')
        ->and($completed->voucher_id)->toBe(42)
        ->and($completed->result_summary)->toMatchArray([
            'success' => true,
            'provider_transfer_succeeded' => true,
        ])
        ->and($completed->completed_at)->not->toBeNull();
});
