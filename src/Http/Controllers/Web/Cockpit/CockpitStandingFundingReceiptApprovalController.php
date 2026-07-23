<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Actions\Funding\ApproveAccountFundingReceipt;
use LBHurtado\XChange\Models\AccountFundingReceipt;

final class CockpitStandingFundingReceiptApprovalController extends Controller
{
    public function __invoke(
        Request $request,
        AccountFundingReceipt $receipt,
        ApproveAccountFundingReceipt $approve,
    ): JsonResponse {
        $actor = $request->user();

        if (! $actor instanceof Model) {
            throw new AuthenticationException;
        }

        $settled = $approve->handle($receipt, $actor);

        return response()->json([
            'schema' => 'x-change.cockpit.account-funding-receipt-approval.v1',
            'receipt' => [
                'reference' => $settled->reference,
                'status' => $settled->status->value,
                'settled_at' => $settled->settled_at?->toIso8601String(),
            ],
            'message' => 'Verified funding was recognized in Treasury Inventory and credited to the Account.',
        ])->withHeaders([
            'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
