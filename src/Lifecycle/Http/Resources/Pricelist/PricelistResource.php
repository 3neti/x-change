<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Http\Resources\Pricelist;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PricelistResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'success' => true,
            'data' => [
                'name' => data_get($this->resource, 'name') !== null
                    ? (string) data_get($this->resource, 'name')
                    : null,
                'currency' => data_get($this->resource, 'currency') !== null
                    ? (string) data_get($this->resource, 'currency')
                    : null,
                'items' => PricelistItemResource::collection(
                    collect(data_get($this->resource, 'items', []))
                ),
                'commercial_offering' => [
                    'reference' => data_get($this->resource, 'commercial_offering.reference'),
                    'version' => data_get($this->resource, 'commercial_offering.version'),
                    'snapshot_hash' => data_get($this->resource, 'commercial_offering.snapshot_hash'),
                    'effective_at' => data_get($this->resource, 'commercial_offering.effective_at'),
                ],
                'catalog' => [
                    'reference' => data_get($this->resource, 'catalog.reference'),
                    'version' => data_get($this->resource, 'catalog.version'),
                ],
            ],
            'meta' => [],
        ];
    }
}
