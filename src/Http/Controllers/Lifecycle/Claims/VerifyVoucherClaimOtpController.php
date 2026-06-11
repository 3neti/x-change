<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Lifecycle\Claims;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Claim\VerifyClaimApprovalOtp;
use LBHurtado\XChange\Services\ApiResponseFactory;

class VerifyVoucherClaimOtpController extends Controller
{
    public function __invoke(
        string $code,
        VerifyClaimApprovalOtp $verifyOtp,
        ApiResponseFactory $responses,
    ): JsonResponse {
        $voucher = Voucher::query()->where('code', $code)->firstOrFail();

        return $responses->success(
            $verifyOtp->handle($voucher, request()->all())
        );
    }
}
