<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Http\Resources\Vouchers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VoucherCancellationResource extends JsonResource
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
                'cancelled' => data_get($this->resource, 'cancelled') !== null
                    ? (bool) data_get($this->resource, 'cancelled')
                    : null,
                'reason' => data_get($this->resource, 'reason') !== null
                    ? (string) data_get($this->resource, 'reason')
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
