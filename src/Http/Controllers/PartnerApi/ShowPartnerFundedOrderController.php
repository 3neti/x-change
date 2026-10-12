<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\PartnerApi;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Models\Checkout;
use LBHurtado\XChange\Services\ApiResponseFactory;
use LBHurtado\XChange\Services\PartnerApi\PartnerApiRequestContext;
use LBHurtado\XChange\Services\PartnerApi\PartnerFundedOrderPresenter;

final class ShowPartnerFundedOrderController extends Controller
{
    public function __invoke(
        string $reference,
        PartnerApiRequestContext $context,
        PartnerFundedOrderPresenter $presenter,
        ApiResponseFactory $responses,
    ): JsonResponse {
        $checkout = Checkout::query()
            ->where('partner_api_client_id', $context->client()->getKey())
            ->whereHas('fundingOrder', static fn ($query) => $query->where('reference', $reference))
            ->firstOrFail();

        return $responses->success($presenter->present($checkout));
    }
}
