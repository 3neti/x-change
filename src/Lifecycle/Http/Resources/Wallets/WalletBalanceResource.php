<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Http\Resources\Wallets;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WalletBalanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'data' => [
                'balance' => $this->resource,
            ],
            'meta' => new \stdClass(),
        ];
    }
}
