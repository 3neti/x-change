<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Http\Resources\Reconciliations;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ReconciliationCollectionResource extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        return [
            'success' => true,
            'data' => [
                'items' => ReconciliationSummaryResource::collection($this->collection),
            ],
            'meta' => [],
        ];
    }
}
