<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\PayCode;

use Illuminate\Routing\Controller;
use LBHurtado\XChange\Actions\PayCode\EstimatePayCodeCost;
use LBHurtado\XChange\Contracts\AuditLoggerContract;
use LBHurtado\XChange\Contracts\UserResolverContract;
use LBHurtado\XChange\Http\Requests\EstimatePayCodeRequest;
use LBHurtado\XChange\Services\ApiResponseFactory;
use Throwable;

class EstimatePayCodeController extends Controller
{
    public function __invoke(
        EstimatePayCodeRequest $request,
        EstimatePayCodeCost $action,
        ApiResponseFactory $responses,
        AuditLoggerContract $audit,
        UserResolverContract $users,
    ) {
        $payload = $request->validated();
        $issuer = $users->resolve($payload);

        $audit->log('pay_code.estimate.requested', [
            'issuer_id' => is_object($issuer) ? ($issuer->id ?? null) : null,
            'amount' => data_get($payload, 'cash.amount'),
            'currency' => data_get($payload, 'cash.currency'),
            'idempotency_key' => $request->header((string) config('x-change.api.idempotency.header', 'Idempotency-Key')),
            'correlation_id' => $request->header((string) config('x-change.api.correlation.header', 'X-Correlation-ID')),
        ]);

        try {
            $result = $action->handle($payload);

            $audit->log('pay_code.estimate.succeeded', [
                'issuer_id' => is_object($issuer) ? ($issuer->id ?? null) : null,
                'amount' => data_get($payload, 'cash.amount'),
                'currency' => data_get($payload, 'cash.currency'),
                'total' => $result->total,
            ]);

            return $responses->success($result, [], 200);
        } catch (Throwable $e) {
            $audit->log('pay_code.estimate.failed', [
                'issuer_id' => is_object($issuer) ? ($issuer->id ?? null) : null,
                'amount' => data_get($payload, 'cash.amount'),
                'currency' => data_get($payload, 'cash.currency'),
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
