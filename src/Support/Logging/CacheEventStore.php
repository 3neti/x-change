<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Support\Logging;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use LBHurtado\XChange\Contracts\EventStoreContract;

class CacheEventStore implements EventStoreContract
{
    protected string $indexKey = 'xchange:events:index';

    public function list(array $filters = []): array
    {
        $ids = Cache::get($this->indexKey, []);

        if (! is_array($ids)) {
            return [];
        }

        $events = collect($ids)
            ->map(fn ($id) => Cache::get($this->eventKey((string) $id)))
            ->filter(fn ($item) => is_array($item))
            ->values();

        if (isset($filters['type']) && is_string($filters['type']) && $filters['type'] !== '') {
            $events = $events->filter(fn (array $event) => ($event['type'] ?? null) === $filters['type'])->values();
        }

        if (isset($filters['resource_type']) && is_string($filters['resource_type']) && $filters['resource_type'] !== '') {
            $events = $events->filter(fn (array $event) => ($event['resource_type'] ?? null) === $filters['resource_type'])->values();
        }

        return $events->all();
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

        Cache::forever($this->eventKey($id), $event);

        $ids = Cache::get($this->indexKey, []);

        if (! is_array($ids)) {
            $ids = [];
        }

        array_unshift($ids, $id);
        $ids = array_values(array_unique(array_map('strval', $ids)));

        Cache::forever($this->indexKey, $ids);
    }

    protected function eventKey(string $id): string
    {
        return 'xchange:event:'.$id;
    }
}
