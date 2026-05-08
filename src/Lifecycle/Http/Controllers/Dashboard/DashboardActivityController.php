<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Http\Controllers\Dashboard;

use Brick\Money\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Models\DisbursementReconciliation;
use LBHurtado\XChange\Models\VoucherClaim;

class DashboardActivityController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $recentVouchers = Voucher::query()
            ->orderByDesc('created_at')
            ->take(10)
            ->get()
            ->map(fn (Voucher $v) => [
                'id' => $v->id,
                'type' => 'voucher',
                'code' => $v->code,
                'amount' => $this->voucherAmount($v),
                'currency' => $this->voucherCurrency($v),
                'status' => $v->display_status,
                'created_at' => $v->created_at?->toIso8601String(),
            ])
            ->values();

        $recentClaims = VoucherClaim::query()
            ->with('voucher')
            ->orderByDesc('created_at')
            ->take(10)
            ->get()
            ->map(fn (VoucherClaim $c) => [
                'id' => $c->id,
                'type' => 'claim',
                'code' => $c->voucher?->code,
                'amount' => $c->disbursed_amount ?? $c->requested_amount ?? 0,
                'currency' => $c->currency ?? 'PHP',
                'status' => $c->status,
                'mobile' => $c->claimer_mobile,
                'created_at' => $c->created_at?->toIso8601String(),
            ])
            ->values();

        $recentReconciliations = DisbursementReconciliation::query()
            ->orderByDesc('created_at')
            ->take(10)
            ->get()
            ->map(fn (DisbursementReconciliation $r) => [
                'id' => $r->id,
                'type' => 'reconciliation',
                'provider' => $r->provider ?? null,
                'status' => $r->status ?? null,
                'needs_review' => $r->needs_review,
                'reference' => $r->reference ?? null,
                'created_at' => $r->created_at?->toIso8601String(),
            ])
            ->values();

        return response()->json([
            'data' => [
                'activity' => [
                    'vouchers' => $recentVouchers,
                    'claims' => $recentClaims,
                    'reconciliations' => $recentReconciliations,
                ],
            ],
        ]);
    }

    protected function voucherAmount(Voucher $voucher): float
    {
        $amount = data_get($voucher, 'cash.amount');

        if ($amount instanceof Money) {
            return $amount->getAmount()->toFloat();
        }

        return is_numeric($amount) ? (float) $amount : 0.0;
    }

    protected function voucherCurrency(Voucher $voucher): string
    {
        $amount = data_get($voucher, 'cash.amount');

        if ($amount instanceof Money) {
            return $amount->getCurrency()->getCurrencyCode();
        }

        $currency = data_get($voucher, 'cash.currency');

        return is_string($currency) && $currency !== '' ? $currency : 'PHP';
    }
}
