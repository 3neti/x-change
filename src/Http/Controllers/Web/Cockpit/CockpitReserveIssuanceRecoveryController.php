<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use LBHurtado\XChange\Models\ReserveTransfer;
use LBHurtado\XChange\Services\Reserve\AdvanceReserveBackedIssuance;
use LBHurtado\XChange\Services\Reserve\ReserveIssuanceStatusPresenter;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class CockpitReserveIssuanceRecoveryController
{
    public function __invoke(Request $request, string $reference, AdvanceReserveBackedIssuance $advance, ReserveIssuanceStatusPresenter $presenter): JsonResponse
    {
        $owner = $request->user();
        abort_unless($owner instanceof Model, 404);
        $transfer = ReserveTransfer::query()->whereMorphedTo('owner', $owner)
            ->where('reference', $reference)->firstOrFail();
        try {
            $transfer = $advance->recoverInterrupted($transfer, $owner);
        } catch (RuntimeException $exception) {
            if ($exception->getMessage() !== 'The bank submission may still be in progress.') {
                throw $exception;
            }
            throw new ConflictHttpException($exception->getMessage(), $exception);
        }

        return response()->json($presenter->present($transfer))->header('Cache-Control', 'no-store');
    }
}
