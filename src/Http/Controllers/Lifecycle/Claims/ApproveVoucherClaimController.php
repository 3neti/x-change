<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Lifecycle\Claims;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\ClaimApprovalExecutionContract;
use LBHurtado\XChange\Services\ApiResponseFactory;

class ApproveVoucherClaimController extends Controller
{
    public function __invoke(
        string $code,
        ClaimApprovalExecutionContract $approval,
        ApiResponseFactory $responses,
    ): JsonResponse {
        $voucher = Voucher::query()->where('code', $code)->firstOrFail();

        return $responses->success(
            $approval->approve($voucher, request()->all())
        );
    }
}
