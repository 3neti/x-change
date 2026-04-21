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
            'data' => [
                'reconciliation' => $this->resource,
            ],
            'meta' => new \stdClass(),
        ];
    }
}
