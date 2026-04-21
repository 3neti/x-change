<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Http\Resources\Pricelist;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PricelistItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'code' => data_get($this->resource, 'code') !== null
                ? (string) data_get($this->resource, 'code')
                : null,
            'name' => data_get($this->resource, 'name') !== null
                ? (string) data_get($this->resource, 'name')
                : null,
            'category' => data_get($this->resource, 'category') !== null
                ? (string) data_get($this->resource, 'category')
                : null,
            'amount' => data_get($this->resource, 'amount') !== null
                ? (float) data_get($this->resource, 'amount')
                : null,
            'currency' => data_get($this->resource, 'currency') !== null
                ? (string) data_get($this->resource, 'currency')
                : null,
            'active' => data_get($this->resource, 'active') !== null
                ? (bool) data_get($this->resource, 'active')
                : null,
        ];
    }
}
