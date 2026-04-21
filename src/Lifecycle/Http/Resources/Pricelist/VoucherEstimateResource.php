<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Http\Resources\Pricelist;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VoucherEstimateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'success' => true,
            'data' => [
                'currency' => (string) $this->resource->currency,
                'base_fee' => (float) $this->resource->base_fee,
                'components' => collect($this->resource->components ?? [])
                    ->map(fn ($component) => [
                        'name' => (string) data_get($component, 'name'),
                        'amount' => (float) data_get($component, 'amount', 0),
                    ])
                    ->values()
                    ->all(),
                'total' => (float) $this->resource->total,
            ],
            'meta' => [],
        ];
    }
}
