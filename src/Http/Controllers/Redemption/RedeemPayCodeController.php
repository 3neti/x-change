<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Redemption;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Redemption\RedeemPayCode;
use LBHurtado\XChange\Contracts\AuditLoggerContract;
use LBHurtado\XChange\Http\Requests\Redemption\RedeemPayCodeRequest;
use LBHurtado\XChange\Services\ApiResponseFactory;
use Throwable;

class RedeemPayCodeController extends Controller
{
    public function __invoke(
        string $code,
        RedeemPayCodeRequest $request,
        RedeemPayCode $action,
        ApiResponseFactory $responses,
        AuditLoggerContract $audit,
    ): JsonResponse {
        $code = strtoupper(trim($code));

        $audit->log('pay_code.redeem.requested', [
            'voucher_code' => $code,
            'mobile' => $request->input('mobile'),
        ]);

        $voucher = Voucher::query()->where('code', $code)->first();

        if (! $voucher) {
            $audit->log('pay_code.redeem.failed', [
                'voucher_code' => $code,
                'reason' => 'voucher_not_found',
            ]);

            return $responses->error(
                message: 'Invalid voucher code.',
                code: 'PAY_CODE_INVALID',
                errors: [
                    'code' => ['Invalid voucher code.'],
                ],
                status: 404,
            );
        }

        try {
            $result = $action->handle($voucher, $request->validated());

            $audit->log('pay_code.redeem.succeeded', [
                'voucher_code' => $code,
                'status' => $result->status,
                'redeemed' => $result->redeemed,
            ]);

            return $responses->success($result, [], 200);
        } catch (Throwable $e) {
            $audit->log('pay_code.redeem.failed', [
                'voucher_code' => $code,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
