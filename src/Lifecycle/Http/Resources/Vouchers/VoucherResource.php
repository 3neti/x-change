<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Http\Resources\Vouchers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VoucherResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'success' => true,
            'data' => [
                'voucher_id' => (int) $this->resource->voucher_id,
                'code' => (string) $this->resource->code,
                'amount' => (float) $this->resource->amount,
                'currency' => (string) $this->resource->currency,

                'issuer' => [
                    'id' => data_get($this->resource, 'issuer.id') !== null
                        ? (int) data_get($this->resource, 'issuer.id')
                        : null,
                ],

                'cost' => [
                    'currency' => (string) data_get($this->resource, 'cost.currency'),
                    'base_fee' => (float) data_get($this->resource, 'cost.base_fee', 0),
                    'components' => collect(data_get($this->resource, 'cost.components', []))
                        ->map(fn ($component) => [
                            'name' => (string) data_get($component, 'name'),
                            'amount' => (float) data_get($component, 'amount', 0),
                        ])
                        ->values()
                        ->all(),
                    'total' => (float) data_get($this->resource, 'cost.total', 0),
                ],

                'wallet' => [
                    'balance_before' => (float) data_get($this->resource, 'wallet.balance_before', 0),
                    'balance_after' => (float) data_get($this->resource, 'wallet.balance_after', 0),
                ],

                'debit' => [
                    'id' => data_get($this->resource, 'debit.id') !== null
                        ? (int) data_get($this->resource, 'debit.id')
                        : null,
                    'amount' => data_get($this->resource, 'debit.amount') !== null
                        ? (float) data_get($this->resource, 'debit.amount')
                        : null,
                ],

                'links' => [
                    'redeem' => (string) data_get($this->resource, 'links.redeem'),
                    'redeem_path' => (string) data_get($this->resource, 'links.redeem_path'),
                ],
            ],
        ];
    }
}
