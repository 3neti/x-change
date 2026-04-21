<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Http\Resources\Issuers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IssuerWalletResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'success' => true,
            'data' => [
                'issuer' => [
                    'id' => data_get($this->resource, 'issuer.id') !== null
                        ? (int) data_get($this->resource, 'issuer.id')
                        : null,
                ],
                'wallet' => [
                    'id' => data_get($this->resource, 'wallet.id') !== null
                        ? (int) data_get($this->resource, 'wallet.id')
                        : null,
                    'slug' => data_get($this->resource, 'wallet.slug') !== null
                        ? (string) data_get($this->resource, 'wallet.slug')
                        : null,
                    'name' => data_get($this->resource, 'wallet.name') !== null
                        ? (string) data_get($this->resource, 'wallet.name')
                        : null,
                    'balance' => data_get($this->resource, 'wallet.balance') !== null
                        ? (float) data_get($this->resource, 'wallet.balance')
                        : null,
                ],
            ],
            'meta' => [],
        ];
    }
}
