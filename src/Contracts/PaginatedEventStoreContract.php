<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

interface PaginatedEventStoreContract
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array{items: array<int, array<string, mixed>>, next_cursor: ?string, has_more: bool}
     */
    public function page(array $filters = []): array;
}
