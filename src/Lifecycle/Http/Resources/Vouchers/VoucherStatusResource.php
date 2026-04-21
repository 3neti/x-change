<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Http\Resources\Vouchers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VoucherStatusResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'success' => true,
            'data' => [
                'voucher_id' => data_get($this->resource, 'voucher_id') !== null
                    ? (int) data_get($this->resource, 'voucher_id')
                    : null,
                'code' => data_get($this->resource, 'code') !== null
                    ? (string) data_get($this->resource, 'code')
                    : null,
                'status' => data_get($this->resource, 'status') !== null
                    ? (string) data_get($this->resource, 'status')
                    : null,
                'claimed' => data_get($this->resource, 'claimed') !== null
                    ? (bool) data_get($this->resource, 'claimed')
                    : null,
                'fully_claimed' => data_get($this->resource, 'fully_claimed') !== null
                    ? (bool) data_get($this->resource, 'fully_claimed')
                    : null,
                'remaining_balance' => data_get($this->resource, 'remaining_balance') !== null
                    ? (float) data_get($this->resource, 'remaining_balance')
                    : null,
                'currency' => data_get($this->resource, 'currency') !== null
                    ? (string) data_get($this->resource, 'currency')
                    : null,
            ],
            'meta' => [],
        ];
    }
}
