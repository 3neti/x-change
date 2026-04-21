<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Http\Resources\Wallets;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class WalletLedgerCollectionResource extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        return [
            'success' => true,
            'data' => [
                'items' => WalletLedgerEntryResource::collection($this->collection),
            ],
            'meta' => [],
        ];
    }
}
