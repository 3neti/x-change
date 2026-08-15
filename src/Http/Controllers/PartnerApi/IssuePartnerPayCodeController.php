<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\PartnerApi;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use LBHurtado\XChange\Actions\PayCode\GeneratePayCode;
use LBHurtado\XChange\Http\Requests\PartnerApi\IssuePartnerPayCodeRequest;
use LBHurtado\XChange\Models\PartnerApiClient;
use LBHurtado\XChange\Models\PartnerApiOperation;
use LBHurtado\XChange\Services\ApiResponseFactory;
use LBHurtado\XChange\Services\IdempotencyService;
use LBHurtado\XChange\Services\PartnerApi\PartnerApiMandateGuard;
use LBHurtado\XChange\Services\PartnerApi\PartnerApiRequestContext;

class IssuePartnerPayCodeController extends Controller
{
    public function __invoke(
        IssuePartnerPayCodeRequest $request,
        PartnerApiRequestContext $context,
        PartnerApiMandateGuard $mandates,
        GeneratePayCode $issue,
        IdempotencyService $idempotency,
        ApiResponseFactory $responses,
    ): JsonResponse {
        $payload = $request->validated();
        $headers = (array) Arr::pull($payload, '_partner', []);
        $key = (string) data_get($headers, 'idempotency_key');
        data_set($payload, 'metadata.issuer_id', (string) $context->issuer()->getKey());
        data_set($payload, '_meta.idempotency_key', $key);
        data_set($payload, '_meta.correlation_id', data_get($headers, 'correlation_id'));

        $outcome = DB::transaction(function () use ($context, $mandates, $payload, $key, $idempotency, $issue, $headers): array {
            $client = PartnerApiClient::query()->lockForUpdate()->findOrFail($context->client()->getKey());
            $context->setClient($client);
            $mandates->assertAllows($payload, $context);
            $namespace = sprintf('partner-api:%s:pay-codes:issue', $client->reference);
            $recalled = $idempotency->recallOrValidate($key, $payload, $namespace);

            if (is_array($recalled)) {
                return ['data' => $recalled, 'replayed' => true];
            }

            $currency = strtoupper((string) data_get($payload, 'cash.currency'));
            $principalMinor = $mandates->principalMinor($payload);
            $mandates->assertDailyPrincipalAvailable($principalMinor, $context);
            $result = $issue->handle($payload);
            $data = $result->toArray();

            PartnerApiOperation::query()->create([
                'partner_api_client_id' => $client->getKey(),
                'operation' => 'pay_code_issued',
                'idempotency_key' => $key,
                'correlation_id' => data_get($headers, 'correlation_id'),
                'subject_reference' => data_get($data, 'code'),
                'principal_minor' => $principalMinor,
                'currency' => $currency,
                'occurred_at' => now(),
            ]);
            $idempotency->remember($key, $payload, $data, $namespace);

            return ['data' => $data, 'replayed' => false];
        }, attempts: 5);

        $correlationId = data_get($headers, 'correlation_id');
        $response = $responses->success(
            $outcome['data'],
            [
                'idempotency' => ['key' => $key, 'replayed' => $outcome['replayed']],
                'correlation_id' => $correlationId,
            ],
            $outcome['replayed'] ? 200 : 201,
        );

        if (is_string($correlationId) && $correlationId !== '') {
            $response->headers->set(
                (string) config('x-change.api.correlation.header', 'X-Correlation-ID'),
                $correlationId,
            );
        }

        return $response;
    }
}
