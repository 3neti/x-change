<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Http\Resources\Wallets;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WalletTopUpResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'success' => true,
            'data' => [
                'top_up' => [
                    'id' => data_get($this->resource, 'id') !== null
                        ? (int) data_get($this->resource, 'id')
                        : null,
                    'wallet_id' => data_get($this->resource, 'wallet_id') !== null
                        ? (int) data_get($this->resource, 'wallet_id')
                        : null,
                    'amount' => data_get($this->resource, 'amount') !== null
                        ? (float) data_get($this->resource, 'amount')
                        : null,
                    'currency' => data_get($this->resource, 'currency') !== null
                        ? (string) data_get($this->resource, 'currency')
                        : null,
                    'reference' => data_get($this->resource, 'reference') !== null
                        ? (string) data_get($this->resource, 'reference')
                        : null,
                    'status' => data_get($this->resource, 'status') !== null
                        ? (string) data_get($this->resource, 'status')
                        : null,
                ],
            ],
            'meta' => [],
        ];
    }
}
