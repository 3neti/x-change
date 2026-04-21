<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Http\Controllers\Vouchers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Contracts\VoucherLifecycleServiceContract;
use LBHurtado\XChange\Lifecycle\Http\Resources\Vouchers\VoucherDetailResource;

class ShowVoucherByCodeController extends Controller
{
    public function __invoke(
        string $code,
        VoucherLifecycleServiceContract $vouchers,
    ): JsonResponse {
        $code = strtoupper(trim($code));

        $result = $vouchers->showByCode($code);

        return VoucherDetailResource::make($result)
            ->response()
            ->setStatusCode(200);
    }
}
