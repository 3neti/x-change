<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Http\Controllers\Claims;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Contracts\RedemptionCompletionServiceContract;
use LBHurtado\XChange\Lifecycle\Http\Requests\Claims\CompleteVoucherClaimRequest;
use LBHurtado\XChange\Lifecycle\Http\Resources\Claims\VoucherClaimCompletionResource;

class CompleteVoucherClaimController extends Controller
{
    public function __invoke(
        string $code,
        CompleteVoucherClaimRequest $request,
        RedemptionCompletionServiceContract $completions,
    ): JsonResponse {
        $code = strtoupper(trim($code));

        $result = $completions->complete($code, $request->validated());

        return VoucherClaimCompletionResource::make($result)
            ->response()
            ->setStatusCode(200);
    }
}
