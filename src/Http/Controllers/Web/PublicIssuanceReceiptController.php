<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use LBHurtado\XChange\Models\IssuanceBatch;
use LBHurtado\XChange\Models\PayCodeIssuanceFundingOrder;
use LBHurtado\XChange\Services\IssuanceBatch\IssuanceBatchAccess;
use LBHurtado\XChange\Services\PublicIssuance\PublicIssuanceOrderAccess;
use LBHurtado\XChange\Services\PublicIssuance\PublicIssuanceReceiptPresenter;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class PublicIssuanceReceiptController extends Controller
{
    public function __invoke(
        Request $request,
        PayCodeIssuanceFundingOrder $order,
        PublicIssuanceReceiptPresenter $presenter,
        PublicIssuanceOrderAccess $access,
        IssuanceBatchAccess $batches,
    ): Response {
        abort_unless(
            $request->hasValidSignature()
            && is_string(data_get($order->metadata, 'public_auto_generate.token_hash')),
            404,
        );

        $canManage = false;
        try {
            $access->authorize($order, $request);
            $canManage = true;
        } catch (NotFoundHttpException) {
            // Signed receipt links remain read-only outside the recovery browser session.
        }

        $batch = IssuanceBatch::query()
            ->where('funding_order_id', $order->getKey())
            ->first();

        if ($canManage && $batch instanceof IssuanceBatch) {
            // The signed receipt is the continuation of a verified recovery session.
            // Grant the same short-lived workspace access used by the batch pages.
            $batches->grantSession($request, $batch);
        }

        return Inertia::render('x-change/public/IssuanceReceipt', [
            'receipt' => $presenter->present($order, $canManage, $batch),
        ])->rootView('x-change::claim-root')
            ->toResponse($request)
            ->withHeaders([
                'Cache-Control' => 'private, no-store',
                'Referrer-Policy' => 'no-referrer',
                'X-Robots-Tag' => 'noindex, nofollow, noarchive',
            ]);
    }
}
