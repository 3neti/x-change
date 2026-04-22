<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Http\Resources\Users;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'success' => true,
            'data' => [
                'user' => [
                    'id' => data_get($this->resource, 'id') !== null
                        ? (string) data_get($this->resource, 'id')
                        : null,
                    'name' => data_get($this->resource, 'name') !== null
                        ? (string) data_get($this->resource, 'name')
                        : null,
                    'email' => data_get($this->resource, 'email') !== null
                        ? (string) data_get($this->resource, 'email')
                        : null,
                    'mobile' => data_get($this->resource, 'mobile') !== null
                        ? (string) data_get($this->resource, 'mobile')
                        : null,
                    'country' => data_get($this->resource, 'country') !== null
                        ? (string) data_get($this->resource, 'country')
                        : null,
                    'status' => data_get($this->resource, 'status') !== null
                        ? (string) data_get($this->resource, 'status')
                        : null,
                ],
            ],
            'meta' => [],
        ];
    }
}
