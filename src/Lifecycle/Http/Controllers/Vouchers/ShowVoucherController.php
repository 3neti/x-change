<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Http\Controllers\Vouchers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Contracts\VoucherLifecycleServiceContract;
use LBHurtado\XChange\Lifecycle\Http\Resources\Vouchers\VoucherDetailResource;

class ShowVoucherController extends Controller
{
    public function __invoke(
        string $voucher,
        VoucherLifecycleServiceContract $vouchers,
    ): JsonResponse {
        $result = $vouchers->show($voucher);

        return VoucherDetailResource::make($result)
            ->response()
            ->setStatusCode(200);
    }
}
