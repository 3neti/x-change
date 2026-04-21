<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Http\Resources\Wallets;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WalletResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'success' => true,
            'data' => [
                'wallet' => [
                    'id' => data_get($this->resource, 'id') !== null
                        ? (int) data_get($this->resource, 'id')
                        : null,
                    'slug' => data_get($this->resource, 'slug') !== null
                        ? (string) data_get($this->resource, 'slug')
                        : null,
                    'name' => data_get($this->resource, 'name') !== null
                        ? (string) data_get($this->resource, 'name')
                        : null,
                    'balance' => data_get($this->resource, 'balance') !== null
                        ? (float) data_get($this->resource, 'balance')
                        : null,
                    'currency' => data_get($this->resource, 'currency') !== null
                        ? (string) data_get($this->resource, 'currency')
                        : null,
                ],
            ],
            'meta' => [],
        ];
    }
}
