<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Http\Resources\Reconciliations;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReconciliationSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
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
        ];
    }
}
