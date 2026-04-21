<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Http\Controllers\Pricelist;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Lifecycle\Http\Requests\Pricelist\EstimateVoucherRequest;

class EstimateVoucherController extends Controller
{
    public function __invoke(EstimateVoucherRequest $request): JsonResponse
    {
        // TODO: Delegate to LBHurtado\XChange\Actions\PayCode\EstimatePayCodeCost.

        return response()->json([
            'data' => [],
            'meta' => ['message' => 'EstimateVoucherController scaffolded.'],
        ], 501);
    }
}
