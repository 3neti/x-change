<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Http\Controllers\Claims;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Redemption\SubmitPayCodeClaim;
use LBHurtado\XChange\Lifecycle\Http\Requests\Claims\SubmitVoucherClaimRequest;
use LBHurtado\XChange\Lifecycle\Http\Resources\Claims\VoucherClaimSubmissionResource;
use LBHurtado\XChange\Services\ApiResponseFactory;
use LBHurtado\XChange\Services\IdempotencyService;

class SubmitVoucherClaimController extends Controller
{
    public function __invoke(
        SubmitVoucherClaimRequest $request,
        string $code,
        SubmitPayCodeClaim $action,
        ApiResponseFactory $responses,
        IdempotencyService $idempotency,
    ): JsonResponse {
        $code = strtoupper(trim($code));
        $payload = $request->validated();

        $key = $idempotency->extractKey($request);
        $correlationId = $request->header((string) config('x-change.api.correlation.header', 'X-Correlation-ID'));

        $payload['_meta'] = [
            'idempotency_key' => $key,
            'correlation_id' => is_string($correlationId) ? $correlationId : null,
            'voucher_code' => $code,
        ];

        $voucher = Voucher::query()->where('code', $code)->first();

        if (! $voucher) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid voucher code.',
                'code' => 'PAY_CODE_INVALID',
                'errors' => [
                    'code' => ['Invalid voucher code.'],
                ],
            ], 404);
        }

        if (is_string($key)) {
            $recalled = $idempotency->recallOrValidate($key, $payload);

            if (is_array($recalled)) {
                return VoucherClaimSubmissionResource::make((object) $recalled)
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

        $result = $action->handle($voucher, $payload);

        if (is_string($key)) {
            $idempotency->remember($key, $payload, $result->toArray());
        }

        return VoucherClaimSubmissionResource::make($result)
            ->additional([
                'meta' => [
                    'idempotency' => [
                        'key' => $key,
                        'replayed' => false,
                    ],
                ],
            ])
            ->response()
            ->setStatusCode(200);
    }
}
