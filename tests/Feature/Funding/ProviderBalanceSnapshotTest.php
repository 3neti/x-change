<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use LBHurtado\XChange\Models\ProviderBalanceSnapshot;
use LBHurtado\XChange\Services\ProviderBalanceSnapshotStore;

it('stores a sanitized provider balance projection with separate fetch and provider timestamps', function () {
    $store = app(ProviderBalanceSnapshotStore::class);

    $snapshot = $store->recordSuccess('netbank', 'netbank_source_account', [
        'balance_minor' => 150_000,
        'available_balance_minor' => 124_852,
        'currency' => 'PHP',
        'account_number_masked' => '********0001',
        'as_of' => '2026-02-22T08:00:00+08:00',
        'fetched_at' => '2026-07-23T17:45:00+08:00',
    ]);

    expect($snapshot)
        ->toBeInstanceOf(ProviderBalanceSnapshot::class)
        ->and($snapshot->balance_minor)->toBe(150_000)
        ->and($snapshot->available_balance_minor)->toBe(124_852)
        ->and($snapshot->account_reference_masked)->toBe('********0001')
        ->and($snapshot->provider_as_of?->equalTo(CarbonImmutable::parse('2026-02-22T08:00:00+08:00')))->toBeTrue()
        ->and($snapshot->fetched_at?->equalTo(CarbonImmutable::parse('2026-07-23T17:45:00+08:00')))->toBeTrue()
        ->and($snapshot->refresh_status)->toBe('fresh');
});

it('retains the last known balance when a later provider refresh fails', function () {
    $store = app(ProviderBalanceSnapshotStore::class);

    $store->recordSuccess('netbank', 'netbank_source_account', [
        'balance_minor' => 150_000,
        'available_balance_minor' => 124_852,
        'currency' => 'PHP',
        'fetched_at' => now()->subMinute(),
    ]);

    $snapshot = $store->recordFailure(
        'netbank',
        'netbank_source_account',
        'NetBank did not respond.',
    );

    expect($snapshot)->not->toBeNull()
        ->and($snapshot?->available_balance_minor)->toBe(124_852)
        ->and($snapshot?->refresh_status)->toBe('refresh_failed')
        ->and($snapshot?->failure_reason)->toBe('NetBank did not respond.')
        ->and($snapshot?->last_refresh_failed_at)->not->toBeNull();
});
