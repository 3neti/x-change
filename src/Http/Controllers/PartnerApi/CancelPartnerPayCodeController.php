<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\PartnerApi;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use LBHurtado\XChange\Contracts\VoucherLifecycleServiceContract;
use LBHurtado\XChange\Http\Requests\PartnerApi\CancelPartnerPayCodeRequest;
use LBHurtado\XChange\Models\PartnerApiClient;
use LBHurtado\XChange\Models\PartnerApiOperation;
use LBHurtado\XChange\Services\ApiResponseFactory;
use LBHurtado\XChange\Services\IdempotencyService;
use LBHurtado\XChange\Services\PartnerApi\PartnerApiRequestContext;
use LBHurtado\XChange\Services\PartnerApi\PartnerPayCodeReadModel;
use LogicException;

class CancelPartnerPayCodeController extends Controller
{
    public function __invoke(
        string $code,
        CancelPartnerPayCodeRequest $request,
        PartnerApiRequestContext $context,
        PartnerPayCodeReadModel $payCodes,
        VoucherLifecycleServiceContract $lifecycle,
        IdempotencyService $idempotency,
        ApiResponseFactory $responses,
    ): JsonResponse {
        $issuer = $context->issuer();
        $payCodes->find($code, $issuer);
        $payload = $request->validated();
        $headers = (array) Arr::pull($payload, '_partner', []);
        $key = (string) data_get($headers, 'idempotency_key');

        if (! $issuer instanceof Authenticatable) {
            throw new LogicException('Partner API issuer must be an authenticatable Account owner.');
        }

        $outcome = DB::transaction(function () use ($context, $idempotency, $key, $code, $payload, $issuer, $lifecycle, $headers): array {
            $client = PartnerApiClient::query()->lockForUpdate()->findOrFail($context->client()->getKey());
            $context->setClient($client);
            $namespace = sprintf('partner-api:%s:pay-codes:cancel', $client->reference);
            $idempotencyPayload = ['code' => strtoupper($code), ...$payload];
            $recalled = $idempotency->recallOrValidate($key, $idempotencyPayload, $namespace);

            if (is_array($recalled)) {
                return ['data' => $recalled, 'replayed' => true];
            }

            $result = $this->cancelAsIssuer($issuer, $code, $payload, $lifecycle);
            $data = $this->sanitizeResult($result);

            PartnerApiOperation::query()->create([
                'partner_api_client_id' => $client->getKey(),
                'operation' => 'pay_code_cancelled',
                'idempotency_key' => $key,
                'correlation_id' => data_get($headers, 'correlation_id'),
                'subject_reference' => $data['code'],
                'principal_minor' => (int) data_get($data, 'treasury_release.amount_minor', 0),
                'currency' => (string) data_get($data, 'treasury_release.currency', 'PHP'),
                'occurred_at' => now(),
            ]);
            $idempotency->remember($key, $idempotencyPayload, $data, $namespace);

            return ['data' => $data, 'replayed' => false];
        }, attempts: 5);

        $correlationId = data_get($headers, 'correlation_id');
        $response = $responses->success(
            $outcome['data'],
            [
                'idempotency' => ['key' => $key, 'replayed' => $outcome['replayed']],
                'correlation_id' => $correlationId,
            ],
        );

        if (is_string($correlationId) && $correlationId !== '') {
            $response->headers->set(
                (string) config('x-change.api.correlation.header', 'X-Correlation-ID'),
                $correlationId,
            );
        }

        return $response;
    }

    /** @param array<string, mixed> $payload */
    private function cancelAsIssuer(
        Authenticatable $issuer,
        string $code,
        array $payload,
        VoucherLifecycleServiceContract $lifecycle,
    ): array {
        $defaultGuard = Auth::getDefaultDriver();
        Auth::shouldUse('web');
        $guard = Auth::guard('web');
        $previous = $guard->user();
        $guard->setUser($issuer);

        try {
            return (array) $lifecycle->cancel($code, $payload);
        } finally {
            if ($previous instanceof Authenticatable) {
                $guard->setUser($previous);
            } else {
                $guard->logout();
            }

            Auth::shouldUse($defaultGuard);
        }
    }

    /** @param array<string, mixed> $result */
    private function sanitizeResult(array $result): array
    {
        return [
            'schema' => 'x-change.partner-pay-code-cancellation.v1',
            'code' => (string) data_get($result, 'code'),
            'status' => (string) data_get($result, 'status'),
            'cancelled' => (bool) data_get($result, 'cancelled'),
            'reason' => data_get($result, 'reason'),
            'treasury_release' => [
                'released' => (bool) data_get($result, 'treasury_release.released', false),
                'replayed' => (bool) data_get($result, 'treasury_release.replayed', false),
                'amount_minor' => data_get($result, 'treasury_release.amount_minor'),
                'currency' => data_get($result, 'treasury_release.currency'),
            ],
        ];
    }
}
