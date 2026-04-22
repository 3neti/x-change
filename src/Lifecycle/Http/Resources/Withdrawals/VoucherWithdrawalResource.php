<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Http\Resources\Withdrawals;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VoucherWithdrawalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'success' => true,
            'data' => [
                'withdrawal' => [
                    'id' => data_get($this->resource, 'id') !== null
                        ? (string) data_get($this->resource, 'id')
                        : null,
                    'voucher_code' => data_get($this->resource, 'voucher_code') !== null
                        ? (string) data_get($this->resource, 'voucher_code')
                        : null,
                    'status' => data_get($this->resource, 'status') !== null
                        ? (string) data_get($this->resource, 'status')
                        : null,
                    'amount' => data_get($this->resource, 'amount') !== null
                        ? (float) data_get($this->resource, 'amount')
                        : null,
                    'currency' => data_get($this->resource, 'currency') !== null
                        ? (string) data_get($this->resource, 'currency')
                        : null,
                    'bank_code' => data_get($this->resource, 'bank_code') !== null
                        ? (string) data_get($this->resource, 'bank_code')
                        : null,
                    'account_number' => data_get($this->resource, 'account_number') !== null
                        ? (string) data_get($this->resource, 'account_number')
                        : null,
                    'messages' => collect(data_get($this->resource, 'messages', []))
                        ->map(fn ($value) => (string) $value)
                        ->values()
                        ->all(),
                ],
            ],
            'meta' => [],
        ];
    }
}
