<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\PayCode;

use Illuminate\Routing\Controller;
use LBHurtado\XChange\Actions\PayCode\GeneratePayCode;
use LBHurtado\XChange\Http\Requests\GeneratePayCodeRequest;
use LBHurtado\XChange\Services\ApiResponseFactory;
use LBHurtado\XChange\Services\IdempotencyService;

class GeneratePayCodeController extends Controller
{
    public function __invoke(
        GeneratePayCodeRequest $request,
        GeneratePayCode $action,
        ApiResponseFactory $responses,
        IdempotencyService $idempotency,
    ) {
        $payload = $request->validated();
        $key = $idempotency->extractKey($request);

        if (is_string($key)) {
            $recalled = $idempotency->recallOrValidate($key, $payload);

            if (is_array($recalled)) {
                return $responses->success($recalled, [
                    'idempotency' => [
                        'key' => $key,
                        'replayed' => true,
                    ],
                ], 200);
            }
        }

        $result = $action->handle($payload);

        if (is_string($key)) {
            $idempotency->remember($key, $payload, $result);
        }

        return $responses->success($result, [
            'idempotency' => [
                'key' => $key,
                'replayed' => false,
            ],
        ], 201);
    }
}
