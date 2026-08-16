<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\XChange\Contracts\CommercialOfferingResolverContract;
use LBHurtado\XChange\Contracts\PricelistServiceContract;

class PricelistService implements PricelistServiceContract
{
    public function __construct(
        private readonly CommercialOfferingResolverContract $offerings,
    ) {}

    public function showPricelist(): array
    {
        $offering = $this->offerings->resolve('pay_code');

        return [
            'name' => $offering->catalog->reference,
            'currency' => $offering->catalog->currency,
            'items' => $this->itemsFromOffering(),
            'commercial_offering' => [
                'reference' => $offering->reference,
                'version' => $offering->version,
                'snapshot_hash' => $offering->snapshotHash(),
                'effective_at' => $offering->effectiveAt,
            ],
            'catalog' => [
                'reference' => $offering->catalog->reference,
                'version' => $offering->catalog->version,
            ],
        ];
    }

    public function listItems(array $filters = []): array
    {
        $items = $this->itemsFromOffering();

        $category = isset($filters['category']) && is_string($filters['category'])
            ? strtolower($filters['category'])
            : null;

        $active = array_key_exists('active', $filters)
            ? filter_var($filters['active'], FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE)
            : null;

        return array_values(array_filter($items, function (array $item) use ($category, $active): bool {
            if ($category !== null) {
                $itemCategory = is_string($item['category'] ?? null)
                    ? strtolower((string) $item['category'])
                    : null;

                if ($itemCategory !== $category) {
                    return false;
                }
            }

            if ($active !== null) {
                if (($item['active'] ?? null) !== $active) {
                    return false;
                }
            }

            return true;
        }));
    }

    /**
     * @return array<int, array{
     *     code:string|null,
     *     name:string|null,
     *     category:string|null,
     *     amount:float|null,
     *     currency:string|null,
     *     active:bool|null
     * }>
     */
    protected function itemsFromOffering(): array
    {
        $offering = $this->offerings->resolve('pay_code');

        return collect($offering->catalog->items)
            ->map(static fn ($item): array => [
                'code' => $item->reference,
                'name' => $item->label,
                'category' => $item->category,
                'amount_minor' => $item->unitPriceMinor,
                'amount' => $item->unitPriceMinor / 100,
                'currency' => $offering->catalog->currency,
                'active' => ! $item->deprecated,
            ])
            ->values()
            ->all();
    }
}
