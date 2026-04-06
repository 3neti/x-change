<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\PayCode;

use Illuminate\Routing\Controller;
use LBHurtado\XChange\Actions\PayCode\EstimatePayCodeCost;
use LBHurtado\XChange\Http\Requests\EstimatePayCodeRequest;
use LBHurtado\XChange\Services\ApiResponseFactory;

class EstimatePayCodeController extends Controller
{
    public function __invoke(
        EstimatePayCodeRequest $request,
        EstimatePayCodeCost $action,
        ApiResponseFactory $responses,
    ) {
        $result = $action->handle($request->validated());

        return $responses->success($result, [], 200);
    }
}
