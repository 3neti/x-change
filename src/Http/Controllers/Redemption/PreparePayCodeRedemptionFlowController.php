<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Redemption;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Redemption\PreparePayCodeRedemptionFlow;
use LBHurtado\XChange\Contracts\AuditLoggerContract;
use LBHurtado\XChange\Services\ApiResponseFactory;

class PreparePayCodeRedemptionFlowController extends Controller
{
    public function __invoke(
        string $code,
        PreparePayCodeRedemptionFlow $action,
        ApiResponseFactory $responses,
        AuditLoggerContract $audit,
    ): JsonResponse {
        $code = strtoupper(trim($code));

        $audit->log('pay_code.claim.start.requested', [
            'voucher_code' => $code,
        ]);

        $voucher = Voucher::query()->where('code', $code)->first();

        if (! $voucher) {
            $audit->log('pay_code.claim.start.failed', [
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

        $result = $action->handle($voucher);

        $audit->log('pay_code.claim.start.succeeded', [
            'voucher_code' => $code,
            'can_start' => $result->can_start,
            'entry_route' => $result->entry_route,
            'driver_name' => $result->flow->driver_name,
        ]);

        return $responses->success($result, [], 200);
    }
}
