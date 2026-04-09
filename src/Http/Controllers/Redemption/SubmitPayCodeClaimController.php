<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Redemption;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Redemption\SubmitPayCodeClaim;
use LBHurtado\XChange\Contracts\AuditLoggerContract;
use LBHurtado\XChange\Http\Requests\Redemption\SubmitPayCodeClaimRequest;
use LBHurtado\XChange\Services\ApiResponseFactory;
use LBHurtado\XChange\Services\IdempotencyService;
use Throwable;

class SubmitPayCodeClaimController extends Controller
{
    public function __invoke(
        string $code,
        SubmitPayCodeClaimRequest $request,
        SubmitPayCodeClaim $action,
        ApiResponseFactory $responses,
        IdempotencyService $idempotency,
        AuditLoggerContract $audit,
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

        $audit->log('pay_code.claim.submit.requested', [
            'voucher_code' => $code,
            'mobile' => data_get($payload, 'mobile'),
            'amount' => data_get($payload, 'amount'),
            'idempotency_key' => $key,
            'correlation_id' => $correlationId,
        ]);

        $voucher = Voucher::query()->where('code', $code)->first();

        if (! $voucher) {
            $audit->log('pay_code.claim.submit.failed', [
                'voucher_code' => $code,
                'reason' => 'voucher_not_found',
                'idempotency_key' => $key,
            ]);

            return $responses->error(
                message: 'Invalid voucher code.',
                code: 'PAY_CODE_INVALID',
                errors: [
                    'code' => ['Invalid voucher code.'],
                ],
                status: 404,
            );
        }

        try {
            if (is_string($key)) {
                $recalled = $idempotency->recallOrValidate($key, $payload);

                if (is_array($recalled)) {
                    $audit->log('pay_code.claim.submit.succeeded', [
                        'voucher_code' => $code,
                        'claim_type' => data_get($recalled, 'claim_type'),
                        'status' => data_get($recalled, 'status'),
                        'claimed' => data_get($recalled, 'claimed'),
                        'replayed' => true,
                        'idempotency_key' => $key,
                    ]);

                    return $responses->success($recalled, [
                        'idempotency' => [
                            'key' => $key,
                            'replayed' => true,
                        ],
                    ], 200);
                }
            }

            $result = $action->handle($voucher, $payload);

            if (is_string($key)) {
                $idempotency->remember($key, $payload, $result->toArray());
            }

            $audit->log('pay_code.claim.submit.succeeded', [
                'voucher_code' => $code,
                'claim_type' => $result->claim_type,
                'status' => $result->status,
                'claimed' => $result->claimed,
                'replayed' => false,
                'idempotency_key' => $key,
            ]);

            return $responses->success($result, [
                'idempotency' => [
                    'key' => $key,
                    'replayed' => false,
                ],
            ], 200);
        } catch (Throwable $e) {
            $audit->log('pay_code.claim.submit.failed', [
                'voucher_code' => $code,
                'mobile' => data_get($payload, 'mobile'),
                'amount' => data_get($payload, 'amount'),
                'idempotency_key' => $key,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
