<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\PublicIssuance;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use LBHurtado\XChange\Models\PayCodeIssuanceFundingOrder;
use LBHurtado\XChange\Services\Checkout\CheckoutLifecycle;
use LBHurtado\XChange\Services\Cockpit\OnDemandIssuanceFundingOrderPresenter;
use LBHurtado\XChange\Services\PublicIssuance\PublicIssuanceOrderAccess;

final class PublicCheckoutFundingMethodController extends Controller
{
    public function __invoke(
        Request $request,
        PayCodeIssuanceFundingOrder $order,
        CheckoutLifecycle $checkouts,
        OnDemandIssuanceFundingOrderPresenter $presenter,
    ): JsonResponse {
        $validated = $request->validate([
            'method' => ['required', Rule::in(['qr_ph', 'bank_transfer'])],
            'mobile' => ['required_if:method,bank_transfer', 'nullable', 'string', 'max:32'],
        ]);
        $checkout = $checkouts->place($order);

        if ($validated['method'] === 'bank_transfer') {
            $checkouts->selectBankTransfer($checkout, (string) $validated['mobile']);
        } else {
            $checkouts->selectQrPh($checkout);
        }

        return response()->json($presenter->present(
            $order->refresh(),
            trim((string) $request->header(PublicIssuanceOrderAccess::TokenHeader)),
            true,
        ));
    }
}
