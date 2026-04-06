<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\PayCode;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Actions\PayCode\GeneratePayCode;
use LBHurtado\XChange\Http\Requests\GeneratePayCodeRequest;

class GeneratePayCodeController extends Controller
{
    public function __invoke(
        GeneratePayCodeRequest $request,
        GeneratePayCode $action,
    ): JsonResponse {
        $result = $action->handle($request->validated());

        return response()->json([
            'success' => true,
            'data' => $result,
            'meta' => [],
        ], 201);
    }
}
