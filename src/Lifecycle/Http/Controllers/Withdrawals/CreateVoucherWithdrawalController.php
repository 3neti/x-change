<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Http\Controllers\Withdrawals;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Contracts\WithdrawalLifecycleServiceContract;
use LBHurtado\XChange\Lifecycle\Http\Requests\Withdrawals\CreateVoucherWithdrawalRequest;
use LBHurtado\XChange\Lifecycle\Http\Resources\Withdrawals\VoucherWithdrawalResource;

class CreateVoucherWithdrawalController extends Controller
{
    public function __invoke(
        CreateVoucherWithdrawalRequest $request,
        WithdrawalLifecycleServiceContract $withdrawals,
    ): JsonResponse {
        $result = $withdrawals->create($request->validated());

        return VoucherWithdrawalResource::make($result)
            ->response()
            ->setStatusCode(201);
    }
}
