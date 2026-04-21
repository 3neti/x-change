<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

interface EventLifecycleServiceContract
{
    /**
     * @param  array<string,mixed>  $filters
     * @return array<int, array<string,mixed>>
     */
    public function list(array $filters = []): array;

    /**
     * @return array<string,mixed>|object|null
     */
    public function show(string $event): mixed;

    /**
     * @return array<string,mixed>|object|null
     */
    public function showIdempotencyKey(string $key): mixed;
}
