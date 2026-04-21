<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Http\Resources\Claims;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VoucherClaimSubmissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'success' => true,
            'data' => [
                'voucher_code' => (string) $this->resource->voucher_code,
                'claim_type' => (string) $this->resource->claim_type,
                'claimed' => (bool) $this->resource->claimed,
                'status' => (string) $this->resource->status,
                'requested_amount' => data_get($this->resource, 'requested_amount') !== null
                    ? (float) $this->resource->requested_amount
                    : null,
                'disbursed_amount' => data_get($this->resource, 'disbursed_amount') !== null
                    ? (float) $this->resource->disbursed_amount
                    : null,
                'currency' => data_get($this->resource, 'currency') !== null
                    ? (string) data_get($this->resource, 'currency')
                    : null,
                'remaining_balance' => data_get($this->resource, 'remaining_balance') !== null
                    ? (float) $this->resource->remaining_balance
                    : null,
                'fully_claimed' => (bool) $this->resource->fully_claimed,
                'disbursement' => [
                    'status' => data_get($this->resource, 'disbursement.status') !== null
                        ? (string) data_get($this->resource, 'disbursement.status')
                        : null,
                    'bank_code' => data_get($this->resource, 'disbursement.bank_code') !== null
                        ? (string) data_get($this->resource, 'disbursement.bank_code')
                        : null,
                    'account_number' => data_get($this->resource, 'disbursement.account_number') !== null
                        ? (string) data_get($this->resource, 'disbursement.account_number')
                        : null,
                ],
                'messages' => collect(data_get($this->resource, 'messages', []))
                    ->map(fn ($value) => (string) $value)
                    ->values()
                    ->all(),
            ],
        ];
    }
}
