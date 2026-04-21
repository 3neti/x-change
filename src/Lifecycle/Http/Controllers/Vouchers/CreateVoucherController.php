<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Http\Controllers\Vouchers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Actions\PayCode\GeneratePayCode;
use LBHurtado\XChange\Lifecycle\Http\Requests\Vouchers\CreateVoucherRequest;
use LBHurtado\XChange\Lifecycle\Http\Resources\Vouchers\VoucherResource;
use LBHurtado\XChange\Services\IdempotencyService;

class CreateVoucherController extends Controller
{
    public function __invoke(
        CreateVoucherRequest $request,
        GeneratePayCode $action,
        IdempotencyService $idempotency,
    ): JsonResponse {
        $payload = $request->validated();

        $key = $idempotency->extractKey($request);
        $correlationId = $request->header((string) config('x-change.api.correlation.header', 'X-Correlation-ID'));

        $payload['_meta'] = [
            'idempotency_key' => $key,
            'correlation_id' => is_string($correlationId) ? $correlationId : null,
        ];

        if (is_string($key)) {
            $recalled = $idempotency->recallOrValidate($key, $payload);

            if (is_array($recalled)) {
                return VoucherResource::make((object) $recalled)
                    ->additional([
                        'meta' => [
                            'idempotency' => [
                                'key' => $key,
                                'replayed' => true,
                            ],
                        ],
                    ])
                    ->response()
                    ->setStatusCode(200);
            }
        }

        $result = $action->handle($payload);

        if (is_string($key)) {
            $idempotency->remember($key, $payload, $result->toArray());
        }

        return VoucherResource::make($result)
            ->additional([
                'meta' => [
                    'idempotency' => [
                        'key' => $key,
                        'replayed' => false,
                    ],
                ],
            ])
            ->response()
            ->setStatusCode(201);
    }
}
