<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
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

        $access->bind($order, $request);

        return to_route('x-change.public-auto-generate.show');
    }
}
