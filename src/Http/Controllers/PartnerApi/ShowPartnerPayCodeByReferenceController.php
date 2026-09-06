<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\PartnerApi;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Models\PartnerApiPayCodeReference;
use LBHurtado\XChange\Services\ApiResponseFactory;
use LBHurtado\XChange\Services\PartnerApi\PartnerApiRequestContext;
use LBHurtado\XChange\Services\PartnerApi\PartnerPayCodeReadModel;

class ShowPartnerPayCodeByReferenceController extends Controller
{
    public function __invoke(
        string $externalReference,
        PartnerApiRequestContext $context,
        PartnerPayCodeReadModel $payCodes,
        ApiResponseFactory $responses,
    ): JsonResponse {
        $reference = PartnerApiPayCodeReference::query()
            ->whereBelongsTo($context->client(), 'client')
            ->where('external_reference', $externalReference)
            ->with('voucher:id,code')
            ->firstOrFail();

        return $responses->success(
            $payCodes->find((string) $reference->voucher->code, $context->issuer()),
        );
    }
}
