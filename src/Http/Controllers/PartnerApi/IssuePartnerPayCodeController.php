<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\PartnerApi;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Arr;
use LBHurtado\XChange\Actions\PayCode\GeneratePayCode;
use LBHurtado\XChange\Http\Requests\PartnerApi\IssuePartnerPayCodeRequest;
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
        $mandates->assertAllows($payload, $context);
        data_set($payload, 'metadata.issuer_id', (string) $context->issuer()->getKey());
        data_set($payload, '_meta.idempotency_key', $key);
        data_set($payload, '_meta.correlation_id', data_get($headers, 'correlation_id'));

        $recalled = $idempotency->recallOrValidate($key, $payload);

        if (is_array($recalled)) {
            return $responses->success($recalled, ['idempotency' => ['key' => $key, 'replayed' => true]]);
        }

        $result = $issue->handle($payload);
        $idempotency->remember($key, $payload, $result->toArray());

        return $responses->success(
            $result,
            ['idempotency' => ['key' => $key, 'replayed' => false]],
            201,
        );
    }
}
