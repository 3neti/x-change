<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\PartnerApi;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Actions\PayCode\EstimatePayCodeCost;
use LBHurtado\XChange\Http\Requests\PartnerApi\EstimatePartnerPayCodeRequest;
use LBHurtado\XChange\Services\ApiResponseFactory;
use LBHurtado\XChange\Services\PartnerApi\PartnerApiMandateGuard;
use LBHurtado\XChange\Services\PartnerApi\PartnerApiRequestContext;

class EstimatePartnerPayCodeController extends Controller
{
    public function __invoke(
        EstimatePartnerPayCodeRequest $request,
        PartnerApiRequestContext $context,
        PartnerApiMandateGuard $mandates,
        EstimatePayCodeCost $estimate,
        ApiResponseFactory $responses,
    ): JsonResponse {
        $payload = $request->validated();
        $mandates->assertAllows($payload, $context);
        data_set($payload, 'metadata.issuer_id', (string) $context->issuer()->getKey());

        return $responses->success($estimate->handle($payload));
    }
}
