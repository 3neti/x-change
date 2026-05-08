<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Http\Controllers\Dashboard;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use LBHurtado\Voucher\Enums\VoucherState;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Models\DisbursementReconciliation;
use LBHurtado\XChange\Models\VoucherClaim;

class DashboardStatsController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $vouchers = Voucher::query();

        $totalCount = (clone $vouchers)->count();
        $activeCount = (clone $vouchers)->where('state', VoucherState::ACTIVE)->count();
        $redeemedCount = (clone $vouchers)->whereNotNull('redeemed_at')->count();
        $expiredCount = (clone $vouchers)->where('expires_at', '<', now())->count();
        $cancelledCount = (clone $vouchers)->whereIn('state', [VoucherState::CLOSED, VoucherState::CANCELLED])->count();

        $claims = VoucherClaim::query();
        $totalClaims = (clone $claims)->count();
        $succeededClaims = (clone $claims)->where('status', 'succeeded')->count();
        $failedClaims = (clone $claims)->where('status', 'failed')->count();
        $totalDisbursedMinor = (clone $claims)->where('status', 'succeeded')->sum('disbursed_amount_minor');

        $reconciliations = DisbursementReconciliation::query();
        $needsReviewCount = (clone $reconciliations)->where('needs_review', true)->count();

        $successRate = $totalClaims > 0
            ? round(($succeededClaims / $totalClaims) * 100, 1)
            : 0.0;

        return response()->json([
            'data' => [
                'stats' => [
                    'vouchers' => [
                        'total' => $totalCount,
                        'active' => $activeCount,
                        'redeemed' => $redeemedCount,
                        'expired' => $expiredCount,
                        'cancelled' => $cancelledCount,
                    ],
                    'disbursements' => [
                        'total_attempts' => $totalClaims,
                        'successful' => $succeededClaims,
                        'failed' => $failedClaims,
                        'success_rate' => $successRate,
                        'total_disbursed' => $totalDisbursedMinor / 100,
                        'currency' => 'PHP',
                    ],
                    'reconciliations' => [
                        'needs_review' => $needsReviewCount,
                    ],
                ],
            ],
        ]);
    }
}
