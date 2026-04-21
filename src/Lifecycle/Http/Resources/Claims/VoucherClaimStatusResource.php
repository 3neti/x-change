<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Http\Resources\Claims;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VoucherClaimStatusResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'success' => true,
            'data' => [
                'voucher_code' => data_get($this->resource, 'voucher_code') !== null
                    ? (string) data_get($this->resource, 'voucher_code')
                    : null,
                'claim_type' => data_get($this->resource, 'claim_type') !== null
                    ? (string) data_get($this->resource, 'claim_type')
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
                'requested_amount' => data_get($this->resource, 'requested_amount') !== null
                    ? (float) data_get($this->resource, 'requested_amount')
                    : null,
                'disbursed_amount' => data_get($this->resource, 'disbursed_amount') !== null
                    ? (float) data_get($this->resource, 'disbursed_amount')
                    : null,
                'remaining_balance' => data_get($this->resource, 'remaining_balance') !== null
                    ? (float) data_get($this->resource, 'remaining_balance')
                    : null,
                'currency' => data_get($this->resource, 'currency') !== null
                    ? (string) data_get($this->resource, 'currency')
                    : null,
                'messages' => collect(data_get($this->resource, 'messages', []))
                    ->map(fn ($value) => (string) $value)
                    ->values()
                    ->all(),
            ],
            'meta' => [],
        ];
    }
}
