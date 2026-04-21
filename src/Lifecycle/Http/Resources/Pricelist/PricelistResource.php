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
            ],
            'meta' => [],
        ];
    }
}
