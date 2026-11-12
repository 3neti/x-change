<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\PartnerApi;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use LBHurtado\XChange\Actions\PartnerApi\CreatePartnerAccountFundedOrder;
use LBHurtado\XChange\Http\Requests\PartnerApi\CreatePartnerAccountFundedOrderRequest;
use LBHurtado\XChange\Models\Checkout;
use LBHurtado\XChange\Services\ApiResponseFactory;
use LBHurtado\XChange\Services\Checkout\CheckoutLifecycle;
use LBHurtado\XChange\Services\PartnerApi\PartnerApiRequestContext;
use LBHurtado\XChange\Services\PartnerApi\PartnerFundedOrderPresenter;

final class CreatePartnerAccountFundedOrderController extends Controller
{
    public function __invoke(CreatePartnerAccountFundedOrderRequest $request, PartnerApiRequestContext $context, CreatePartnerAccountFundedOrder $create, CheckoutLifecycle $checkouts, PartnerFundedOrderPresenter $presenter, ApiResponseFactory $responses): JsonResponse
    {
        abort_unless((bool) config('x-change.partner_api.account_funded_orders.enabled', false), 404);
        abort_unless((bool) config('x-change.issuance_funding.on_demand.enabled', false), 503);
        $payload = $request->validated();
        $method = (string) Arr::pull($payload, 'funding_method');
        $mobile = Arr::pull($payload, 'payer_mobile');
        $reference = (string) Arr::pull($payload, 'external_reference');
        $headers = (array) Arr::pull($payload, '_partner', []);
        $key = trim((string) data_get($headers, 'idempotency_key'));
        abort_unless((bool) config($method === 'qr_ph' ? 'x-change.issuance_funding.on_demand.fixed_qr_ph.enabled' : 'x-change.funding.requests.bank_transfer.enabled', false), 422);
        $outcome = $create->handle($context->client(), $reference, $key, $payload, $method, $mobile);
        $checkout = $checkouts->place($outcome['order']);
        $checkout = DB::transaction(function () use ($checkout, $context): Checkout {
            $checkout = Checkout::query()->lockForUpdate()->findOrFail($checkout->getKey());
            abort_if($checkout->partner_api_client_id !== null && (int) $checkout->partner_api_client_id !== (int) $context->client()->getKey(), 404);
            if ($checkout->partner_api_client_id === null) {
                $checkout->forceFill(['partner_api_client_id' => $context->client()->getKey()])->save();
            }

            return $checkout;
        });
        if ($method === 'bank_transfer' && $checkout->selected_method !== 'bank_transfer' && $outcome['order']->fundingIntent !== null && $outcome['order']->status->value === 'awaiting_payment') {
            $checkout = $checkouts->selectBankTransfer($checkout, (string) $mobile);
        }

        return $responses->success($presenter->present($checkout), ['idempotency' => ['replayed' => $outcome['replayed']]], $outcome['replayed'] ? 200 : 201);
    }
}
