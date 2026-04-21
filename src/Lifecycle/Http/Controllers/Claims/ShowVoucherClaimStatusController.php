<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Http\Controllers\Claims;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Contracts\RedemptionCompletionServiceContract;
use LBHurtado\XChange\Lifecycle\Http\Resources\Claims\VoucherClaimStatusResource;

class ShowVoucherClaimStatusController extends Controller
{
    public function __invoke(
        string $code,
        RedemptionCompletionServiceContract $completions,
    ): JsonResponse {
        $code = strtoupper(trim($code));

        $result = $completions->status($code);

        return VoucherClaimStatusResource::make($result)
            ->response()
            ->setStatusCode(200);
    }
}
