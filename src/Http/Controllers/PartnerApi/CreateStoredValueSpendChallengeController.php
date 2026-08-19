<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\PartnerApi;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Http\Requests\PartnerApi\CreateStoredValueSpendChallengeRequest;
use LBHurtado\XChange\Services\ApiResponseFactory;
use LBHurtado\XChange\Services\PartnerApi\PartnerStoredValueSpendChallengeService;

final class CreateStoredValueSpendChallengeController extends Controller
{
    public function __invoke(
        CreateStoredValueSpendChallengeRequest $request,
        string $instrument,
        PartnerStoredValueSpendChallengeService $challenges,
        ApiResponseFactory $responses,
    ): JsonResponse {
        $payload = $request->validated();
        $outcome = $challenges->create($instrument, $payload);

        $correlationId = data_get($payload, '_partner.correlation_id');
        $response = $responses->success($outcome['data'], [
            'idempotency' => [
                'key' => data_get($payload, '_partner.idempotency_key'),
                'replayed' => $outcome['replayed'],
            ],
            'correlation_id' => $correlationId,
        ], $outcome['replayed'] ? 200 : 201);

        if (is_string($correlationId) && $correlationId !== '') {
            $response->headers->set(
                (string) config('x-change.api.correlation.header', 'X-Correlation-ID'),
                $correlationId,
            );
        }

        return $response;
    }
}
