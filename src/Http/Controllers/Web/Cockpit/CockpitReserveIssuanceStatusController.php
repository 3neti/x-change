<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use LBHurtado\XChange\Models\ReserveTransfer;
use LBHurtado\XChange\Services\Reserve\ReserveIssuanceStatusPresenter;

final class CockpitReserveIssuanceStatusController
{
    public function __invoke(Request $request, string $reference, ReserveIssuanceStatusPresenter $presenter): JsonResponse
    {
        $owner = $request->user();
        abort_unless($owner instanceof Model, 404);
        $transfer = ReserveTransfer::query()->whereMorphedTo('owner', $owner)
            ->where('reference', $reference)->firstOrFail();

        return response()->json($presenter->present($transfer))->header('Cache-Control', 'no-store');
    }
}
