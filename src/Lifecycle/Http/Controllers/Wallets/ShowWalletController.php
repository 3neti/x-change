<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Http\Controllers\Wallets;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Contracts\WalletAccessContract;
use LBHurtado\XChange\Lifecycle\Http\Resources\Wallets\WalletResource;

class ShowWalletController extends Controller
{
    public function __invoke(string $wallet, WalletAccessContract $wallets): JsonResponse
    {
        $result = $wallets->find($wallet);

        return WalletResource::make($result)
            ->response()
            ->setStatusCode(200);
    }
}
