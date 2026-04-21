<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Http\Resources\Reconciliations;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReconciliationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'success' => true,
            'data' => [
                'reconciliation' => [
                    'id' => data_get($this->resource, 'id') !== null
                        ? (string) data_get($this->resource, 'id')
                        : null,
                    'reference' => data_get($this->resource, 'reference') !== null
                        ? (string) data_get($this->resource, 'reference')
                        : null,
                    'status' => data_get($this->resource, 'status') !== null
                        ? (string) data_get($this->resource, 'status')
                        : null,
                    'provider_status' => data_get($this->resource, 'provider_status') !== null
                        ? (string) data_get($this->resource, 'provider_status')
                        : null,
                    'amount' => data_get($this->resource, 'amount') !== null
                        ? (float) data_get($this->resource, 'amount')
                        : null,
                    'currency' => data_get($this->resource, 'currency') !== null
                        ? (string) data_get($this->resource, 'currency')
                        : null,
                    'reason' => data_get($this->resource, 'reason') !== null
                        ? (string) data_get($this->resource, 'reason')
                        : null,
                    'resolved' => data_get($this->resource, 'resolved') !== null
                        ? (bool) data_get($this->resource, 'resolved')
                        : null,
                    'resolved_at' => data_get($this->resource, 'resolved_at') !== null
                        ? (string) data_get($this->resource, 'resolved_at')
                        : null,
                ],
            ],
            'meta' => [],
        ];
    }
}
