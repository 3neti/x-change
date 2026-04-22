<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Http\Resources\Withdrawals;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class VoucherWithdrawalCollectionResource extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        return [
            'success' => true,
            'data' => [
                'items' => VoucherWithdrawalSummaryResource::collection($this->collection),
            ],
            'meta' => [],
        ];
    }
}
