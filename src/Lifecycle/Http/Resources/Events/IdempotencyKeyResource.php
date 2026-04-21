<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Http\Resources\Events;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IdempotencyKeyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'success' => true,
            'data' => [
                'idempotency' => [
                    'key' => data_get($this->resource, 'key') !== null
                        ? (string) data_get($this->resource, 'key')
                        : null,
                    'replayed' => data_get($this->resource, 'replayed') !== null
                        ? (bool) data_get($this->resource, 'replayed')
                        : null,
                    'first_seen_at' => data_get($this->resource, 'first_seen_at') !== null
                        ? (string) data_get($this->resource, 'first_seen_at')
                        : null,
                    'last_seen_at' => data_get($this->resource, 'last_seen_at') !== null
                        ? (string) data_get($this->resource, 'last_seen_at')
                        : null,
                    'request_fingerprint' => data_get($this->resource, 'request_fingerprint') !== null
                        ? (string) data_get($this->resource, 'request_fingerprint')
                        : null,
                    'response_status' => data_get($this->resource, 'response_status') !== null
                        ? (int) data_get($this->resource, 'response_status')
                        : null,
                ],
            ],
            'meta' => [],
        ];
    }
}
