<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Http\Resources\Claims;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VoucherClaimCompletionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'success' => true,
            'data' => [
                'voucher_code' => data_get($this->resource, 'voucher_code') !== null
                    ? (string) data_get($this->resource, 'voucher_code')
                    : null,
                'reference_id' => data_get($this->resource, 'reference_id') !== null
                    ? (string) data_get($this->resource, 'reference_id')
                    : null,
                'status' => data_get($this->resource, 'status') !== null
                    ? (string) data_get($this->resource, 'status')
                    : null,
                'completed' => data_get($this->resource, 'completed') !== null
                    ? (bool) data_get($this->resource, 'completed')
                    : null,
                'notes' => data_get($this->resource, 'notes') !== null
                    ? (string) data_get($this->resource, 'notes')
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
