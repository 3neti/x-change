<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use LBHurtado\XChange\Contracts\IdempotencyStoreContract;

class CacheIdempotencyStore implements IdempotencyStoreContract
{
    public function __construct(
        protected CacheRepository $cache,
    ) {}

    public function find(string $key): ?array
    {
        $value = $this->cache->get($this->cacheKey($key));

        return is_array($value) ? $value : null;
    }

    public function put(string $key, array $record, int $ttlSeconds): void
    {
        $this->cache->put($this->cacheKey($key), $record, $ttlSeconds);
    }

    protected function cacheKey(string $key): string
    {
        return 'x-change:idempotency:'.$key;
    }
}
