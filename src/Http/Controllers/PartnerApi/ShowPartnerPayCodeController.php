<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\PartnerApi;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Services\ApiResponseFactory;
use LBHurtado\XChange\Services\PartnerApi\PartnerApiRequestContext;
use LBHurtado\XChange\Services\PartnerApi\PartnerPayCodeReadModel;

class ShowPartnerPayCodeController extends Controller
{
    public function __invoke(
        string $code,
        PartnerApiRequestContext $context,
        PartnerPayCodeReadModel $payCodes,
        ApiResponseFactory $responses,
    ): JsonResponse {
        return $responses->success($payCodes->find($code, $context->issuer()));
    }
}
