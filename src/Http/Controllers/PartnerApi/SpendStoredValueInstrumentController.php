<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\PartnerApi;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Http\Requests\PartnerApi\SpendStoredValueInstrumentRequest;
use LBHurtado\XChange\Services\ApiResponseFactory;
use LBHurtado\XChange\Services\PartnerApi\PartnerStoredValueInstrumentService;

final class SpendStoredValueInstrumentController extends Controller
{
    public function __invoke(
        SpendStoredValueInstrumentRequest $request,
        string $instrument,
        PartnerStoredValueInstrumentService $instruments,
        ApiResponseFactory $responses,
    ): JsonResponse {
        $payload = $request->validated();
        $outcome = $instruments->spend($instrument, $payload);
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
