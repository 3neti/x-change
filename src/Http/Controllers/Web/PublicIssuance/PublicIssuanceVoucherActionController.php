<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\PublicIssuance;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Models\PayCodeIssuanceFundingOrder;
use LBHurtado\XChange\Services\PublicIssuance\PublicIssuanceVoucherActions;

final class PublicIssuanceVoucherActionController extends Controller
{
    public function __invoke(Request $request, PayCodeIssuanceFundingOrder $order, PublicIssuanceVoucherActions $actions): JsonResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'in:extend,cancel,expire,request_refund'],
            'extension_hours' => ['nullable', 'integer', 'in:24,168'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);
        abort_unless($order->voucher !== null, 404);

        $result = $actions->handle(
            $order->voucher,
            $validated['action'],
            isset($validated['extension_hours']) ? (int) $validated['extension_hours'] : null,
            (string) ($validated['reason'] ?? 'Public issuance recovery'),
        );

        return response()->json([
            'message' => data_get($result, 'messages.0', 'Pay Code lifecycle updated.'),
            'result' => $result,
        ]);
    }
}
