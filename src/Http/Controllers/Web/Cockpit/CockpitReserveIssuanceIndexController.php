<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Models\ReserveTransfer;
use LBHurtado\XChange\Services\Reserve\ReserveIssuanceStatusPresenter;

final class CockpitReserveIssuanceIndexController
{
    public function __invoke(Request $request, ReserveIssuanceStatusPresenter $presenter): JsonResponse
    {
        $owner = $request->user();
        abort_unless($owner instanceof Model, 404);
        $transfers = ReserveTransfer::query()->whereMorphedTo('owner', $owner)
            ->whereIn('status', ['held', 'submitting', 'pending', 'provider_settled', 'treasury_posted', 'uncertain', 'review_required', 'issued'])
            ->latest('id')->limit(25)->get();

        $codes = Voucher::query()->whereKey($transfers->pluck('voucher_id')->filter()->all())->pluck('code', 'id');

        return response()->json(['issuances' => $transfers->map(fn (ReserveTransfer $transfer): array => $presenter->present($transfer, $codes->get($transfer->voucher_id), true))->all()])
            ->header('Cache-Control', 'no-store');
    }
}
