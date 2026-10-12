<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\PartnerApi;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Actions\PartnerApi\CreatePartnerFundedOrder;
use LBHurtado\XChange\Http\Requests\PartnerApi\CreateFundedOrderRequest;
use LBHurtado\XChange\Services\ApiResponseFactory;
use LBHurtado\XChange\Services\PartnerApi\PartnerApiRequestContext;
use LBHurtado\XChange\Services\PartnerApi\PartnerFundedOrderPresenter;

final class CreatePartnerFundedOrderController extends Controller
{
    public function __invoke(
        CreateFundedOrderRequest $request,
        PartnerApiRequestContext $context,
        CreatePartnerFundedOrder $create,
        PartnerFundedOrderPresenter $presenter,
        ApiResponseFactory $responses,
    ): JsonResponse {
        $validated = $request->validated();
        $method = $validated['funding_method'];
        abort_unless((bool) config($method === 'qr_ph'
            ? 'x-change.issuance_funding.on_demand.fixed_qr_ph.enabled'
            : 'x-change.funding.requests.bank_transfer.enabled', false), 422);

        $result = $create->handle(
            $context->client(),
            $validated,
            trim((string) $request->header((string) config('x-change.api.idempotency.header', 'Idempotency-Key'))),
        );

        return $responses->success(
            $presenter->present($result['checkout']),
            ['idempotency' => ['replayed' => $result['replayed']]],
            $result['replayed'] ? 200 : 201,
        );
    }
}
