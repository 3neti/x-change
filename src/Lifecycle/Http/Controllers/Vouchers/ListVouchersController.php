<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Http\Controllers\Vouchers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Contracts\VoucherLifecycleServiceContract;
use LBHurtado\XChange\Lifecycle\Http\Requests\Vouchers\ListVouchersRequest;
use LBHurtado\XChange\Lifecycle\Http\Resources\Vouchers\VoucherCollectionResource;

class ListVouchersController extends Controller
{
    public function __invoke(
        ListVouchersRequest $request,
        VoucherLifecycleServiceContract $vouchers,
    ): JsonResponse {
        $result = $vouchers->list($request->validated());

        return VoucherCollectionResource::make($result)
            ->response()
            ->setStatusCode(200);
    }
}
