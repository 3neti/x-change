<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Http\Controllers\Wallets;

use Dedoc\Scramble\Attributes\ExcludeAllRoutesFromDocs;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

#[ExcludeAllRoutesFromDocs]
class ShowWalletBalanceController extends Controller
{
    public function __invoke(mixed $wallet): JsonResponse
    {
        // TODO: Delegate to LBHurtado\XChange\Actions\Wallet\GetWalletBalance
        // and serialize with WalletBalanceResource.

        return response()->json([
            'data' => [],
            'meta' => ['message' => 'ShowWalletBalanceController scaffolded.'],
        ], 501);
    }
}
