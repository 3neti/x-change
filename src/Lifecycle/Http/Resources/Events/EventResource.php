<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Http\Resources\Events;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'success' => true,
            'data' => [
                'event' => [
                    'id' => data_get($this->resource, 'id') !== null
                        ? (string) data_get($this->resource, 'id')
                        : null,
                    'type' => data_get($this->resource, 'type') !== null
                        ? (string) data_get($this->resource, 'type')
                        : null,
                    'status' => data_get($this->resource, 'status') !== null
                        ? (string) data_get($this->resource, 'status')
                        : null,
                    'actor' => data_get($this->resource, 'actor') !== null
                        ? (string) data_get($this->resource, 'actor')
                        : null,
                    'resource_type' => data_get($this->resource, 'resource_type') !== null
                        ? (string) data_get($this->resource, 'resource_type')
                        : null,
                    'resource_id' => data_get($this->resource, 'resource_id') !== null
                        ? (string) data_get($this->resource, 'resource_id')
                        : null,
                    'correlation_id' => data_get($this->resource, 'correlation_id') !== null
                        ? (string) data_get($this->resource, 'correlation_id')
                        : null,
                    'idempotency_key' => data_get($this->resource, 'idempotency_key') !== null
                        ? (string) data_get($this->resource, 'idempotency_key')
                        : null,
                    'occurred_at' => data_get($this->resource, 'occurred_at') !== null
                        ? (string) data_get($this->resource, 'occurred_at')
                        : null,
                    'payload' => (array) data_get($this->resource, 'payload', []),
                ],
            ],
            'meta' => [],
        ];
    }
}
