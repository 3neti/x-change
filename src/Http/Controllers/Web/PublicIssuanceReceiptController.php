<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use LBHurtado\XChange\Models\PayCodeIssuanceFundingOrder;
use LBHurtado\XChange\Services\PublicIssuance\PublicIssuanceReceiptPresenter;
use Symfony\Component\HttpFoundation\Response;

final class PublicIssuanceReceiptController extends Controller
{
    public function __invoke(
        Request $request,
        PayCodeIssuanceFundingOrder $order,
        PublicIssuanceReceiptPresenter $presenter,
    ): Response {
        abort_unless(
            $request->hasValidSignature()
            && is_string(data_get($order->metadata, 'public_auto_generate.token_hash')),
            404,
        );

        return Inertia::render('x-change/public/IssuanceReceipt', [
            'receipt' => $presenter->present($order),
        ])->rootView('x-change::claim-root')
            ->toResponse($request)
            ->withHeaders([
                'Cache-Control' => 'private, no-store',
                'Referrer-Policy' => 'no-referrer',
                'X-Robots-Tag' => 'noindex, nofollow, noarchive',
            ]);
    }
}
