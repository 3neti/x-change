<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Http\Controllers\Claims;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Lifecycle\Http\Requests\Claims\SubmitVoucherClaimRequest;

class SubmitVoucherClaimController extends Controller
{
    public function __invoke(SubmitVoucherClaimRequest $request, string $code): JsonResponse
    {
        // TODO: Delegate to LBHurtado\XChange\Actions\Redemption\SubmitPayCodeClaim.

        return response()->json([
            'data' => [],
            'meta' => ['message' => 'SubmitVoucherClaimController scaffolded.'],
        ], 501);
    }
}
