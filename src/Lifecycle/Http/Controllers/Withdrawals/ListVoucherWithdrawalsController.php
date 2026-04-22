<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Http\Controllers\Withdrawals;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Contracts\WithdrawalLifecycleServiceContract;
use LBHurtado\XChange\Lifecycle\Http\Resources\Withdrawals\VoucherWithdrawalCollectionResource;

class ListVoucherWithdrawalsController extends Controller
{
    public function __invoke(
        Request $request,
        WithdrawalLifecycleServiceContract $withdrawals,
    ): JsonResponse {
        $result = $withdrawals->list($request->query());

        return VoucherWithdrawalCollectionResource::make($result)
            ->response()
            ->setStatusCode(200);
    }
}
