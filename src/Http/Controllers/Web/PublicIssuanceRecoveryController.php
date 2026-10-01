<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\URL;
use LBHurtado\XChange\Enums\PayCodeIssuanceFundingOrderStatus;
use LBHurtado\XChange\Models\PayCodeIssuanceFundingOrder;
use LBHurtado\XChange\Services\PublicIssuance\PublicIssuanceOrderAccess;

final class PublicIssuanceRecoveryController extends Controller
{
    public function __invoke(
        Request $request,
        PayCodeIssuanceFundingOrder $order,
        PublicIssuanceOrderAccess $access,
    ): RedirectResponse {
        abort_unless(
            $request->hasValidSignature()
            && is_string(data_get($order->metadata, 'public_auto_generate.token_hash')),
            404,
        );

        if (in_array($order->status, [
            PayCodeIssuanceFundingOrderStatus::Issued,
            PayCodeIssuanceFundingOrderStatus::Cancelled,
            PayCodeIssuanceFundingOrderStatus::Expired,
        ], true)) {
            return redirect()->to(URL::temporarySignedRoute(
                'x-change.public-auto-generate.receipt',
                now()->addDays(max(1, (int) config('x-change.public_auto_generate.receipt_link_ttl_days', 30))),
                ['order' => $order->reference],
            ))->withHeaders([
                'Cache-Control' => 'private, no-store',
                'Referrer-Policy' => 'no-referrer',
                'X-Robots-Tag' => 'noindex, nofollow, noarchive',
            ]);
        }

        $access->bind($order, $request);

        return to_route('x-change.public-auto-generate.show');
    }
}
