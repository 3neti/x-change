<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Http\Controllers\Vouchers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Contracts\VoucherLifecycleServiceContract;
use LBHurtado\XChange\Lifecycle\Http\Requests\Vouchers\CancelVoucherRequest;
use LBHurtado\XChange\Lifecycle\Http\Resources\Vouchers\VoucherCancellationResource;

class CancelVoucherController extends Controller
{
    public function __invoke(
        string $voucher,
        CancelVoucherRequest $request,
        VoucherLifecycleServiceContract $vouchers,
    ): JsonResponse {
        $result = $vouchers->cancel($voucher, $request->validated());

        return VoucherCancellationResource::make($result)
            ->response()
            ->setStatusCode(200);
    }
}
