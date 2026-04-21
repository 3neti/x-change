<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Http\Controllers\Vouchers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Contracts\VoucherLifecycleServiceContract;
use LBHurtado\XChange\Lifecycle\Http\Resources\Vouchers\VoucherStatusResource;

class ShowVoucherStatusController extends Controller
{
    public function __invoke(
        string $voucher,
        VoucherLifecycleServiceContract $vouchers,
    ): JsonResponse {
        $result = $vouchers->status($voucher);

        return VoucherStatusResource::make($result)
            ->response()
            ->setStatusCode(200);
    }
}
