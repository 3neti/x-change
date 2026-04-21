<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Http\Resources\Wallets;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WalletLedgerEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => data_get($this->resource, 'id') !== null
                ? (int) data_get($this->resource, 'id')
                : null,
            'type' => data_get($this->resource, 'type') !== null
                ? (string) data_get($this->resource, 'type')
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
            'created_at' => data_get($this->resource, 'created_at') !== null
                ? (string) data_get($this->resource, 'created_at')
                : null,
        ];
    }
}
