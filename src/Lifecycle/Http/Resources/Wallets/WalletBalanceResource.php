<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Http\Resources\Wallets;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WalletBalanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'success' => true,
            'data' => [
                'wallet_id' => data_get($this->resource, 'wallet_id') !== null
                    ? (int) data_get($this->resource, 'wallet_id')
                    : null,
                'balance' => data_get($this->resource, 'balance') !== null
                    ? (float) data_get($this->resource, 'balance')
                    : null,
                'currency' => data_get($this->resource, 'currency') !== null
                    ? (string) data_get($this->resource, 'currency')
                    : null,
            ],
            'meta' => [],
        ];
    }
}
