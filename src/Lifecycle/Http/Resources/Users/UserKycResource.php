<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Http\Resources\Users;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserKycResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'success' => true,
            'data' => [
                'kyc' => [
                    'user_id' => data_get($this->resource, 'user_id') !== null
                        ? (string) data_get($this->resource, 'user_id')
                        : null,
                    'status' => data_get($this->resource, 'status') !== null
                        ? (string) data_get($this->resource, 'status')
                        : null,
                    'transaction_id' => data_get($this->resource, 'transaction_id') !== null
                        ? (string) data_get($this->resource, 'transaction_id')
                        : null,
                    'provider' => data_get($this->resource, 'provider') !== null
                        ? (string) data_get($this->resource, 'provider')
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
