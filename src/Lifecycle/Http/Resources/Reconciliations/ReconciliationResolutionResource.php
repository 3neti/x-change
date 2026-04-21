<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Http\Resources\Reconciliations;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReconciliationResolutionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'success' => true,
            'data' => [
                'reconciliation_id' => data_get($this->resource, 'reconciliation_id') !== null
                    ? (string) data_get($this->resource, 'reconciliation_id')
                    : null,
                'status' => data_get($this->resource, 'status') !== null
                    ? (string) data_get($this->resource, 'status')
                    : null,
                'resolution' => data_get($this->resource, 'resolution') !== null
                    ? (string) data_get($this->resource, 'resolution')
                    : null,
                'resolved' => data_get($this->resource, 'resolved') !== null
                    ? (bool) data_get($this->resource, 'resolved')
                    : null,
                'notes' => data_get($this->resource, 'notes') !== null
                    ? (string) data_get($this->resource, 'notes')
                    : null,
                'messages' => collect(data_get($this->resource, 'messages', []))
                    ->map(fn ($value) => (string) $value)
                    ->values()
                    ->all(),
            ],
            'meta' => [],
        ];
    }
}
