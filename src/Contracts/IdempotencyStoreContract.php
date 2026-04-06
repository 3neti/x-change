<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

interface IdempotencyStoreContract
{
    /**
     * @return array<string, mixed>|null
     */
    public function find(string $key): ?array;

    /**
     * @param  array<string, mixed>  $record
     */
    public function put(string $key, array $record, int $ttlSeconds): void;
}
