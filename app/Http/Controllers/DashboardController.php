<?php

namespace App\Http\Controllers;

use LBHurtado\Voucher\Data\VoucherData;
use Spatie\LaravelData\DataCollection;
use LBHurtado\Voucher\Models\Voucher;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the voucher dashboard for the authenticated user.
     *
     * This method fetches two sets of vouchers:
     *   - Redeemed vouchers: already claimed by the user
     *   - Redeemable vouchers: still unclaimed and available for redemption
     *
     * For each, it computes:
     *   - The total outstanding or redeemed cash amount per currency
     *   - The number of vouchers and the latest `created_at` timestamp
     *
     * These are passed to the Inertia Dashboard page as props:
     *   - `vouchers`: a DataCollection of redeemed VoucherData
     *   - `totalRedeemed`: object keyed by currency, with amount, count, and latest_created_at
     *   - `totalRedeemables`: same as above, for unredeemed vouchers
     *
     * @param Request $request The incoming HTTP request
     * @return Response The Inertia response to render the dashboard
     */
    public function __invoke(Request $request): Response
    {
        // Fetch redeemed vouchers for this user
        $redeemed_vouchers = Voucher::query()
            ->withOwner($request->user())
            ->withRedeemed()
            ->latest('redeemed_at')
            ->get();

        // Summarize totals for redeemed vouchers
        $totalRedeemed = voucher_totals($redeemed_vouchers);

        // Fetch redeemable (unredeemed) vouchers for this user
        $redeemable_vouchers = Voucher::query()
            ->withOwner($request->user())
            ->withRedeemable()
            ->latest('redeemed_at')
            ->get();

        // Summarize totals for redeemable vouchers
        $totalRedeemables = voucher_totals($redeemable_vouchers);

        // Return data to the Vue Dashboard component
        return Inertia::render('Dashboard', [
            'vouchers' => new DataCollection(VoucherData::class, $redeemed_vouchers),
            'totalRedeemed' => $totalRedeemed,
            'totalRedeemables' => $totalRedeemables,
        ]);
    }
}
