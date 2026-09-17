<?php

declare(strict_types=1);

use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use LBHurtado\XChange\Contracts\AppendableEventStoreContract;
use LBHurtado\XChange\Support\Logging\CacheAuditLogger;
use LBHurtado\XChange\Support\Logging\CacheEventStore;

beforeEach(function (): void {
    Cache::flush();

    config()->set('x-change.audit.cache.bucket_size', 3);
    config()->set('x-change.audit.cache.max_recent_buckets', 2);
    config()->set('x-change.audit.cache.event_ttl', 3_600);
    config()->set('x-change.audit.cache.default_list_limit', 2);
    config()->set('x-change.audit.cache.max_list_limit', 4);
    config()->set('x-change.audit.cache.lock_seconds', 10);
    config()->set('x-change.audit.cache.lock_wait_seconds', 0);
});

it('keeps event buckets and the recent bucket index bounded', function (): void {
    $store = app(CacheEventStore::class);

    foreach (range(1, 7) as $number) {
        $store->append([
            'id' => 'evt-'.$number,
            'type' => 'funding.synced',
            'resource_type' => 'funding_address',
        ]);
    }

    $bucketKeys = Cache::get('xchange:events:v2:recent-buckets');

    expect($bucketKeys)->toBeArray()->toHaveCount(2);

    expect(array_map(fn (string $bucketKey): int => count(Cache::get($bucketKey)), $bucketKeys))
        ->toBe([1, 3]);

    expect(array_column($store->list(['limit' => 100]), 'id'))
        ->toBe(['evt-7', 'evt-6', 'evt-5', 'evt-4']);
});

it('applies filters while respecting default and maximum list limits', function (): void {
    $store = app(CacheEventStore::class);

    foreach (range(1, 6) as $number) {
        $store->append([
            'id' => 'evt-'.$number,
            'type' => $number % 2 === 0 ? 'funding.synced' : 'voucher.created',
            'resource_type' => $number <= 3 ? 'voucher' : 'funding_address',
        ]);
    }

    expect($store->list())->toHaveCount(2)
        ->and(array_column($store->list([
            'type' => 'funding.synced',
            'resource_type' => 'funding_address',
            'limit' => 100,
        ]), 'id'))->toBe(['evt-6', 'evt-4']);
});

it('never reads the legacy unbounded event index', function (): void {
    Cache::forever('xchange:events:index', array_fill(0, 50_000, 'legacy-event'));

    $store = app(CacheEventStore::class);
    $store->append([
        'id' => 'evt-current',
        'type' => 'funding.synced',
        'resource_type' => 'funding_address',
    ]);

    expect($store->list(['limit' => 10]))->toHaveCount(1)
        ->and($store->find('evt-current'))->toMatchArray(['id' => 'evt-current'])
        ->and(Cache::get('xchange:events:index'))->toHaveCount(50_000);
});

it('serializes appends behind the distributed event lock', function (): void {
    $lock = Cache::lock('xchange:events:v2:append-lock', 10);
    $lock->get();

    try {
        expect(fn () => app(CacheEventStore::class)->append([
            'id' => 'evt-contended',
            'type' => 'funding.synced',
        ]))->toThrow(LockTimeoutException::class);
    } finally {
        $lock->release();
    }

    app(CacheEventStore::class)->append([
        'id' => 'evt-after-lock',
        'type' => 'funding.synced',
    ]);

    expect(app(CacheEventStore::class)->find('evt-after-lock'))->not->toBeNull();
});

it('writes audit events through the event store contract', function (): void {
    $store = Mockery::mock(AppendableEventStoreContract::class);
    $store->shouldReceive('append')
        ->once()
        ->with(Mockery::on(fn (array $event): bool => $event['type'] === 'funding.synced'
            && $event['resource_type'] === 'funding_address'));

    (new CacheAuditLogger($store))->log('funding.synced', [
        'resource_type' => 'funding_address',
        'resource_id' => 'address-1',
    ]);
});
