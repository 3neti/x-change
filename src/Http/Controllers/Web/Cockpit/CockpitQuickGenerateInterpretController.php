<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Actions\PayCode\EstimatePayCodeCost;
use LBHurtado\XChange\Contracts\VoucherInstructionInterpreter;
use Throwable;

final class CockpitQuickGenerateInterpretController extends Controller
{
    public function __invoke(
        Request $request,
        VoucherInstructionInterpreter $interpreter,
        EstimatePayCodeCost $estimator,
    ): JsonResponse {
        $validated = $request->validate([
            'prompt' => ['required', 'string', 'max:1000'],
        ]);

        $interpretation = $interpreter->interpret($validated['prompt']);

        if (
            ($interpretation['status'] ?? null) === 'ready'
            && is_array($interpretation['instructions'] ?? null)
        ) {
            try {
                $interpretation['pricing'] = $estimator
                    ->handle($interpretation['instructions'])
                    ->toArray();
            } catch (Throwable) {
                // Interpretation remains useful when pricing is temporarily unavailable.
                // The client will show an unavailable estimate instead of guessing from
                // the Pay Code principal.
                $interpretation['pricing'] = null;
            }
        }

        return response()->json($interpretation);
    }
}
