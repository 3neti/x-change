<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Http\Controllers\Claims;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Redemption\PreparePayCodeRedemptionFlow;
use LBHurtado\XChange\Lifecycle\Http\Requests\Claims\StartVoucherClaimRequest;
use LBHurtado\XChange\Lifecycle\Http\Resources\Claims\VoucherClaimStartResource;
use LBHurtado\XChange\Services\ApiResponseFactory;

class StartVoucherClaimController extends Controller
{
    public function __invoke(
        StartVoucherClaimRequest $request,
        string $code,
        PreparePayCodeRedemptionFlow $action,
        ApiResponseFactory $responses,
    ): JsonResponse {
        $code = strtoupper(trim($code));

        $voucher = Voucher::query()->where('code', $code)->first();

        if (! $voucher) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid voucher code.',
                'code' => 'PAY_CODE_INVALID',
                'errors' => [
                    'code' => ['Invalid voucher code.'],
                ],
            ], 404);
        }

        $result = $action->handle($voucher);

        return VoucherClaimStartResource::make($result)
            ->response()
            ->setStatusCode(200);
    }
}
