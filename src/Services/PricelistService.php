<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\XChange\Contracts\PricelistServiceContract;

class PricelistService implements PricelistServiceContract
{
    public function showPricelist(): array
    {
        $currency = (string) config('x-change.pricing.currency', 'PHP');
        $items = $this->itemsFromConfig();

        return [
            'name' => (string) config('x-change.pricing.name', 'Default Pricelist'),
            'currency' => $currency,
            'items' => $items,
        ];
    }

    public function listItems(array $filters = []): array
    {
        $items = $this->itemsFromConfig();

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
    protected function itemsFromConfig(): array
    {
        $currency = (string) config('x-change.pricing.currency', 'PHP');
        $baseFee = (float) config('x-change.pricing.base_fee', 0.0);
        $components = (array) config('x-change.pricing.components', []);

        $items = [[
            'code' => 'base_fee',
            'name' => 'Base Fee',
            'category' => 'base',
            'amount' => $baseFee,
            'currency' => $currency,
            'active' => true,
        ]];

        foreach ($components as $code => $amount) {
            $normalizedCode = is_string($code) ? $code : null;

            $items[] = [
                'code' => $normalizedCode,
                'name' => $normalizedCode !== null
                    ? str_replace('_', ' ', ucfirst($normalizedCode))
                    : null,
                'category' => 'component',
                'amount' => is_numeric($amount) ? (float) $amount : null,
                'currency' => $currency,
                'active' => true,
            ];
        }

        return $items;
    }
}
