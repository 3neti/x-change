<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Contracts\VoucherInstructionInterpreter;

final class CockpitQuickGenerateInterpretController extends Controller
{
    public function __invoke(Request $request, VoucherInstructionInterpreter $interpreter): JsonResponse
    {
        $validated = $request->validate([
            'prompt' => ['required', 'string', 'max:1000'],
        ]);

        return response()->json($interpreter->interpret($validated['prompt']));
    }
}
