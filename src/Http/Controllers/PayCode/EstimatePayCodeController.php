<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\PayCode;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Actions\PayCode\EstimatePayCodeCost;
use LBHurtado\XChange\Http\Requests\EstimatePayCodeRequest;

class EstimatePayCodeController extends Controller
{
    public function __invoke(
        EstimatePayCodeRequest $request,
        EstimatePayCodeCost $action,
    ): JsonResponse {
        $result = $action->handle($request->validated());

        return response()->json([
            'success' => true,
            'data' => $result,
            'meta' => [],
        ]);
    }
}
