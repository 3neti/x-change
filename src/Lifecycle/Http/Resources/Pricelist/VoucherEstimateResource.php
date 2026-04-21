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
            'data' => [
                'estimate' => $this->resource,
            ],
            'meta' => new \stdClass(),
        ];
    }
}
