<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Http\Controllers\Withdrawals;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Contracts\WithdrawalLifecycleServiceContract;
use LBHurtado\XChange\Lifecycle\Http\Resources\Withdrawals\VoucherWithdrawalResource;

class ShowVoucherWithdrawalController extends Controller
{
    public function __invoke(
        string $withdrawal,
        WithdrawalLifecycleServiceContract $withdrawals,
    ): JsonResponse {
        $result = $withdrawals->show($withdrawal);

        return VoucherWithdrawalResource::make($result)
            ->response()
            ->setStatusCode(200);
    }
}
