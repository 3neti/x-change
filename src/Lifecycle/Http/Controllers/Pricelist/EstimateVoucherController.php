<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Http\Controllers\Pricelist;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Actions\PayCode\EstimatePayCodeCost;
use LBHurtado\XChange\Lifecycle\Http\Requests\Pricelist\EstimateVoucherRequest;
use LBHurtado\XChange\Lifecycle\Http\Resources\Pricelist\VoucherEstimateResource;

class EstimateVoucherController extends Controller
{
    public function __invoke(
        EstimateVoucherRequest $request,
        EstimatePayCodeCost $action,
    ): JsonResponse {
        $result = $action->handle($request->validated());

        return VoucherEstimateResource::make($result)
            ->response()
            ->setStatusCode(200);
    }
}
