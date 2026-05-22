<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Http\Resources\Vouchers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use LBHurtado\XRider\Contracts\RiderExperienceResolverContract;
use LBHurtado\XRider\Data\RiderSubjectData;
use LBHurtado\XRider\Enums\RiderOutcomeState;

class VoucherDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $voucher = [
            'id' => data_get($this->resource, 'id') !== null
                ? (int) data_get($this->resource, 'id')
                : null,
            'voucher_id' => data_get($this->resource, 'voucher_id') !== null
                ? (int) data_get($this->resource, 'voucher_id')
                : null,
            'code' => data_get($this->resource, 'code') !== null
                ? (string) data_get($this->resource, 'code')
                : null,
            'amount' => data_get($this->resource, 'amount') !== null
                ? (float) data_get($this->resource, 'amount')
                : null,
            'currency' => data_get($this->resource, 'currency') !== null
                ? (string) data_get($this->resource, 'currency')
                : null,
            'status' => data_get($this->resource, 'status') !== null
                ? (string) data_get($this->resource, 'status')
                : null,
            'issuer_id' => data_get($this->resource, 'issuer_id') !== null
                ? (int) data_get($this->resource, 'issuer_id')
                : null,
            'claimed' => data_get($this->resource, 'claimed') !== null
                ? (bool) data_get($this->resource, 'claimed')
                : null,
            'fully_claimed' => data_get($this->resource, 'fully_claimed') !== null
                ? (bool) data_get($this->resource, 'fully_claimed')
                : null,
            'created_at' => data_get($this->resource, 'created_at'),
            'expires_at' => data_get($this->resource, 'expires_at'),
            'starts_at' => data_get($this->resource, 'starts_at'),
            'redeemed_at' => data_get($this->resource, 'redeemed_at'),
            'instructions' => data_get($this->resource, 'instructions'),
            'rider' => $this->riderPayload(),
        ];

        return [
            'success' => true,
            'data' => [
                'voucher' => $voucher,
            ],
            'meta' => [],
        ];
    }

    protected function riderPayload(): ?array
    {
        try {
            $code = data_get($this->resource, 'code');

            if (! filled($code)) {
                return null;
            }

            $subject = new RiderSubjectData(
                type: 'voucher',
                id: data_get($this->resource, 'voucher_id') ?? data_get($this->resource, 'id') ?? $code,
                code: (string) $code,
                meta: [
                    'voucher_id' => data_get($this->resource, 'voucher_id') ?? data_get($this->resource, 'id'),
                    'voucher_code' => (string) $code,
                    'amount' => data_get($this->resource, 'amount'),
                    'currency' => data_get($this->resource, 'currency'),
                    'source' => 'voucher-preview',
                ],
            );

            $experience = app(RiderExperienceResolverContract::class)->resolve($subject, [
                'state' => RiderOutcomeState::AcceptedSuccess->value,
                'rider' => data_get($this->resource, 'instructions.rider', []),
                'meta' => [
                    'source' => 'x-change',
                    'route' => 'voucher.preview',
                ],
            ]);

            return [
                'state' => $experience->normalizedState(),
                'preClaim' => $experience->preClaim?->toArray(),
                'stages' => $experience->stages?->toArray(),
                'meta' => $experience->meta,
            ];
        } catch (\Throwable) {
            return null;
        }
    }
}
