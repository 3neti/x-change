<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Http\Controllers\Vouchers;

use LBHurtado\XChange\Actions\Payment\CollectVoucherFunds;
use LBHurtado\XChange\Lifecycle\Http\Requests\Vouchers\ConfirmVoucherPaymentRequest;
use LBHurtado\XChange\Services\ApiResponseFactory;
use LBHurtado\XChange\Services\VoucherAccessService;
use LBHurtado\XChange\Services\VoucherCapabilityGuard;

class ConfirmVoucherPaymentController
{
    public function __invoke(
        string $code,
        ConfirmVoucherPaymentRequest $request,
        VoucherAccessService $vouchers,
        CollectVoucherFunds $collect,
        ApiResponseFactory $responses,
        VoucherCapabilityGuard $guard,
    ) {
        $voucher = $vouchers->findByCodeOrFail($code);

        $guard->ensureCanCollect($voucher);

        $result = $collect->handle(
            voucher: $voucher,
            payload: $request->payload(),
        );

        return $responses->success(
            data: $result->toArray(),
            meta: [
                'message' => 'Voucher payment confirmation processed.',
            ],
        );
    }
}
