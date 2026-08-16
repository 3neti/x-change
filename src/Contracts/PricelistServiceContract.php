<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

interface PricelistServiceContract
{
    /**
     * Return the active pricelist summary and embedded items.
     *
     * @return array{
     *     name:string|null,
     *     currency:string|null,
     *     items:array<int, array{
     *         code:string|null,
     *         name:string|null,
     *         category:string|null,
     *         amount:float|null,
     *         amount_minor?:int|null,
     *         currency:string|null,
     *         active:bool|null
     *     }>,
     *     commercial_offering?:array{reference:string,version:int,snapshot_hash:string,effective_at:string},
     *     catalog?:array{reference:string,version:int}
     * }
     */
    public function showPricelist(): array;

    /**
     * Return a filtered list of pricelist items.
     *
     * @param  array<string,mixed>  $filters
     * @return array<int, array{
     *     code:string|null,
     *     name:string|null,
     *     category:string|null,
     *     amount:float|null,
     *     amount_minor?:int|null,
     *     currency:string|null,
     *     active:bool|null
     * }>
     */
    public function listItems(array $filters = []): array;
}
