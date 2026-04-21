<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

interface EventStoreContract
{
    /**
     * @param  array<string,mixed>  $filters
     * @return array<int, array<string,mixed>>
     */
    public function list(array $filters = []): array;

    /**
     * @return array<string,mixed>|null
     */
    public function find(string $id): ?array;
}
