<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Http\Resources\Issuers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IssuerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'data' => [
                'issuer' => $this->resource,
            ],
            'meta' => new \stdClass(),
        ];
    }
}
