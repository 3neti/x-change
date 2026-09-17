<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Support\Logging;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use LBHurtado\XChange\Contracts\AppendableEventStoreContract;
use LBHurtado\XChange\Contracts\EventStoreContract;

class CacheEventStore implements AppendableEventStoreContract, EventStoreContract
{
    protected string $recentBucketsKey = 'xchange:events:v2:recent-buckets';

    protected string $appendLockKey = 'xchange:events:v2:append-lock';

    public function list(array $filters = []): array
    {
        $recentBuckets = Cache::get($this->recentBucketsKey, []);

        if (! is_array($recentBuckets)) {
            return [];
        }

        $limit = $this->listLimit($filters['limit'] ?? null);
        $events = [];
        $seen = [];

        foreach (array_slice($recentBuckets, 0, $this->maxRecentBuckets()) as $bucketKey) {
            if (! is_string($bucketKey) || $bucketKey === '') {
                continue;
            }

            $ids = Cache::get($bucketKey, []);

            if (! is_array($ids)) {
                continue;
            }

            $eventKeys = array_map(fn (mixed $id): string => $this->eventKey((string) $id), $ids);
            $cachedEvents = Cache::many($eventKeys);

            foreach ($ids as $id) {
                $id = (string) $id;

                if ($id === '' || isset($seen[$id])) {
                    continue;
                }

                $seen[$id] = true;
                $event = $cachedEvents[$this->eventKey($id)] ?? null;

                if (! is_array($event) || ! $this->matches($event, $filters)) {
                    continue;
                }

                $events[] = $event;

                if (count($events) >= $limit) {
                    return $events;
                }
            }
        }

        return $events;
    }

    public function find(string $id): ?array
    {
        $event = Cache::get($this->eventKey($id));

        return is_array($event) ? $event : null;
    }

    /**
     * @param  array<string,mixed>  $event
     */
    public function append(array $event): void
    {
        $id = (string) ($event['id'] ?? Str::uuid()->toString());
        $event['id'] = $id;

        Cache::lock($this->appendLockKey, $this->lockSeconds())
            ->block($this->lockWaitSeconds(), function () use ($id, $event): void {
                $ttl = $this->eventTtl();
                $recentBuckets = Cache::get($this->recentBucketsKey, []);
                $recentBuckets = is_array($recentBuckets)
                    ? array_values(array_filter($recentBuckets, 'is_string'))
                    : [];

                $bucketKey = $recentBuckets[0] ?? null;
                $ids = is_string($bucketKey) ? Cache::get($bucketKey, []) : [];

                if (! is_string($bucketKey) || ! is_array($ids) || count($ids) >= $this->bucketSize()) {
                    $bucketKey = $this->newBucketKey();
                    $ids = [];
                    array_unshift($recentBuckets, $bucketKey);
                }

                Cache::put($this->eventKey($id), $event, $ttl);

                array_unshift($ids, $id);
                $ids = array_slice(array_values(array_unique(array_map('strval', $ids))), 0, $this->bucketSize());
                Cache::put($bucketKey, $ids, $ttl);

                $recentBuckets = array_slice(
                    array_values(array_unique($recentBuckets)),
                    0,
                    $this->maxRecentBuckets(),
                );
                Cache::put($this->recentBucketsKey, $recentBuckets, $ttl);
            });
    }

    protected function eventKey(string $id): string
    {
        return 'xchange:events:v2:event:'.$id;
    }

    protected function newBucketKey(): string
    {
        return 'xchange:events:v2:bucket:'.now()->utc()->format('YmdHis').'-'.Str::lower(Str::random(8));
    }

    /**
     * @param  array<string,mixed>  $event
     * @param  array<string,mixed>  $filters
     */
    protected function matches(array $event, array $filters): bool
    {
        if (isset($filters['type']) && is_string($filters['type']) && $filters['type'] !== ''
            && ($event['type'] ?? null) !== $filters['type']) {
            return false;
        }

        if (isset($filters['resource_type']) && is_string($filters['resource_type']) && $filters['resource_type'] !== ''
            && ($event['resource_type'] ?? null) !== $filters['resource_type']) {
            return false;
        }

        return true;
    }

    protected function bucketSize(): int
    {
        return max(1, (int) config('x-change.audit.cache.bucket_size', 500));
    }

    protected function maxRecentBuckets(): int
    {
        return max(1, (int) config('x-change.audit.cache.max_recent_buckets', 48));
    }

    protected function eventTtl(): int
    {
        return max(60, (int) config('x-change.audit.cache.event_ttl', 2_592_000));
    }

    protected function listLimit(mixed $requested): int
    {
        $default = max(1, (int) config('x-change.audit.cache.default_list_limit', 50));
        $maximum = max(1, (int) config('x-change.audit.cache.max_list_limit', 100));

        return min($maximum, is_numeric($requested) ? max(1, (int) $requested) : $default);
    }

    protected function lockSeconds(): int
    {
        return max(1, (int) config('x-change.audit.cache.lock_seconds', 10));
    }

    protected function lockWaitSeconds(): int
    {
        return max(0, (int) config('x-change.audit.cache.lock_wait_seconds', 5));
    }
}
