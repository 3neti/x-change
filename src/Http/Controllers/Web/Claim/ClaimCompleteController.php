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
        $flowId = trim((string) $request->input('flow_id', ''));

        Log::info('[ClaimCompleteController] Form flow callback received', [
            'voucher_code' => $code,
            'has_flow_id' => $flowId !== '',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Flow completed, awaiting user confirmation',
        ]);
    }
}
