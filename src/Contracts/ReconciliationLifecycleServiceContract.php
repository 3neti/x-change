<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

interface ReconciliationLifecycleServiceContract
{
    /**
     * @param  array<string,mixed>  $filters
     * @return array<int, array<string,mixed>>
     */
    public function list(array $filters = []): array;

    /**
     * @return array<string,mixed>|object|null
     */
    public function show(string $reconciliation): mixed;

    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>|object
     */
    public function resolve(string $reconciliation, array $payload): mixed;
}
