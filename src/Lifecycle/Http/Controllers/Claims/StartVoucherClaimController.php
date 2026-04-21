<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Http\Controllers\Claims;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Lifecycle\Http\Requests\Claims\StartVoucherClaimRequest;

class StartVoucherClaimController extends Controller
{
    public function __invoke(StartVoucherClaimRequest $request, string $code): JsonResponse
    {
        // TODO: Delegate to LBHurtado\XChange\Actions\Redemption\PreparePayCodeRedemptionFlow.

        return response()->json([
            'data' => [],
            'meta' => ['message' => 'StartVoucherClaimController scaffolded.'],
        ], 501);
    }
}
