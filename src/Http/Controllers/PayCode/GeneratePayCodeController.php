<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\PayCode;

use Illuminate\Routing\Controller;
use LBHurtado\XChange\Actions\PayCode\GeneratePayCode;
use LBHurtado\XChange\Http\Requests\GeneratePayCodeRequest;
use LBHurtado\XChange\Services\ApiResponseFactory;

class GeneratePayCodeController extends Controller
{
    public function __invoke(
        GeneratePayCodeRequest $request,
        GeneratePayCode $action,
        ApiResponseFactory $responses,
    ) {
        $result = $action->handle($request->validated());

        return $responses->success($result, [], 201);
    }
}
