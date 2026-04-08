<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Redemption;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Redemption\LoadPayCodeRedemptionCompletionContext;
use LBHurtado\XChange\Contracts\AuditLoggerContract;
use LBHurtado\XChange\Http\Requests\Redemption\LoadRedemptionCompletionContextRequest;
use LBHurtado\XChange\Services\ApiResponseFactory;

class LoadPayCodeRedemptionCompletionContextController extends Controller
{
    public function __invoke(
        string $code,
        LoadRedemptionCompletionContextRequest $request,
        LoadPayCodeRedemptionCompletionContext $action,
        ApiResponseFactory $responses,
        AuditLoggerContract $audit,
    ): JsonResponse {
        $code = strtoupper(trim($code));

        $audit->log('pay_code.claim.complete.requested', [
            'voucher_code' => $code,
            'reference_id' => $request->input('reference_id'),
            'flow_id' => $request->input('flow_id'),
        ]);

        $voucher = Voucher::query()->where('code', $code)->first();

        if (! $voucher) {
            $audit->log('pay_code.claim.complete.failed', [
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

        $result = $action->handle(
            $voucher,
            $request->input('reference_id'),
            $request->input('flow_id'),
        );

        $audit->log('pay_code.claim.complete.succeeded', [
            'voucher_code' => $code,
            'can_confirm' => $result->can_confirm,
            'reference_id' => $result->reference_id,
            'flow_id' => $result->flow_id,
        ]);

        return $responses->success($result, [], 200);
    }
}
