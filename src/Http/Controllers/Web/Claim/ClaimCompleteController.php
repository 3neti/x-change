<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Claim;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

class ClaimCompleteController extends Controller
{
    public function __invoke(Request $request, string $code): JsonResponse
    {
        $code = strtoupper(trim($code));
        $collectedData = $request->input('collected_data', []);
        $flowId = $request->input('flow_id', '');
        $completedAt = $request->input('completed_at', now()->toIso8601String());

        Log::info('[ClaimCompleteController] Form flow callback received', [
            'voucher_code' => $code,
            'flow_id' => $flowId,
            'collected_data_keys' => array_keys($collectedData),
            'completed_at' => $completedAt,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Flow completed, awaiting user confirmation',
        ]);
    }
}
