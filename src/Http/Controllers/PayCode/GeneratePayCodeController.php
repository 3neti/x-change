<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\PayCode;

use Illuminate\Routing\Controller;
use LBHurtado\XChange\Actions\PayCode\GeneratePayCode;
use LBHurtado\XChange\Contracts\AuditLoggerContract;
use LBHurtado\XChange\Contracts\UserResolverContract;
use LBHurtado\XChange\Http\Requests\GeneratePayCodeRequest;
use LBHurtado\XChange\Services\ApiResponseFactory;
use LBHurtado\XChange\Services\IdempotencyService;
use Throwable;

class GeneratePayCodeController extends Controller
{
    public function __invoke(
        GeneratePayCodeRequest $request,
        GeneratePayCode $action,
        ApiResponseFactory $responses,
        IdempotencyService $idempotency,
        AuditLoggerContract $audit,
        UserResolverContract $users,
    ) {
        $payload = $request->validated();
        $key = $idempotency->extractKey($request);
        $issuer = $users->resolve($payload);

        $audit->log('pay_code.generate.requested', [
            'issuer_id' => is_object($issuer) ? ($issuer->id ?? null) : null,
            'amount' => data_get($payload, 'cash.amount'),
            'currency' => data_get($payload, 'cash.currency'),
            'idempotency_key' => $key,
            'correlation_id' => $request->header((string) config('x-change.api.correlation.header', 'X-Correlation-ID')),
        ]);

        try {
            if (is_string($key)) {
                $recalled = $idempotency->recallOrValidate($key, $payload);

                if (is_array($recalled)) {
                    $audit->log('pay_code.generate.succeeded', [
                        'issuer_id' => is_object($issuer) ? ($issuer->id ?? null) : null,
                        'voucher_id' => $recalled['voucher_id'] ?? null,
                        'code' => $recalled['code'] ?? null,
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

            $result = $action->handle($payload);

            if (is_string($key)) {
                $idempotency->remember($key, $payload, $result);
            }

            $audit->log('pay_code.generate.succeeded', [
                'issuer_id' => is_object($issuer) ? ($issuer->id ?? null) : null,
                'voucher_id' => $result['voucher_id'] ?? null,
                'code' => $result['code'] ?? null,
                'debit_id' => data_get($result, 'debit.id'),
                'replayed' => false,
                'idempotency_key' => $key,
            ]);

            return $responses->success($result, [
                'idempotency' => [
                    'key' => $key,
                    'replayed' => false,
                ],
            ], 201);
        } catch (Throwable $e) {
            $audit->log('pay_code.generate.failed', [
                'issuer_id' => is_object($issuer) ? ($issuer->id ?? null) : null,
                'amount' => data_get($payload, 'cash.amount'),
                'currency' => data_get($payload, 'cash.currency'),
                'idempotency_key' => $key,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
