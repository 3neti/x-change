<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Http\Controllers\Wallets;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Contracts\WalletAccessContract;
use LBHurtado\XChange\Lifecycle\Http\Requests\Wallets\CreateWalletTopUpRequest;
use LBHurtado\XChange\Lifecycle\Http\Resources\Wallets\WalletTopUpResource;

class CreateWalletTopUpController extends Controller
{
    public function __invoke(
        string $wallet,
        CreateWalletTopUpRequest $request,
        WalletAccessContract $wallets,
    ): JsonResponse {
        $result = $wallets->topUp($wallet, $request->validated());

        return WalletTopUpResource::make($result)
            ->response()
            ->setStatusCode(201);
    }
}
