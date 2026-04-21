<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Http\Controllers\Wallets;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Contracts\WalletAccessContract;
use LBHurtado\XChange\Lifecycle\Http\Requests\Wallets\ListWalletLedgerRequest;
use LBHurtado\XChange\Lifecycle\Http\Resources\Wallets\WalletLedgerCollectionResource;

class ListWalletLedgerController extends Controller
{
    public function __invoke(
        string $wallet,
        ListWalletLedgerRequest $request,
        WalletAccessContract $wallets,
    ): JsonResponse {
        $result = $wallets->ledger($wallet, $request->validated());

        return WalletLedgerCollectionResource::make($result)
            ->response()
            ->setStatusCode(200);
    }
}
