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
            'success' => true,
            'data' => [
                'issuer' => [
                    'id' => data_get($this->resource, 'issuer.id') !== null
                        ? (int) data_get($this->resource, 'issuer.id')
                        : null,
                    'name' => data_get($this->resource, 'issuer.name') !== null
                        ? (string) data_get($this->resource, 'issuer.name')
                        : null,
                    'email' => data_get($this->resource, 'issuer.email') !== null
                        ? (string) data_get($this->resource, 'issuer.email')
                        : null,
                    'mobile' => data_get($this->resource, 'issuer.mobile') !== null
                        ? (string) data_get($this->resource, 'issuer.mobile')
                        : null,
                    'country' => data_get($this->resource, 'issuer.country') !== null
                        ? (string) data_get($this->resource, 'issuer.country')
                        : null,
                ],
            ],
            'meta' => [],
        ];
    }
}
