<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Http\Controllers\Vouchers;

use LBHurtado\XChange\Actions\Payment\CollectVoucherFunds;
use LBHurtado\XChange\Contracts\VoucherCollectionWalletResolverContract;
use LBHurtado\XChange\Lifecycle\Http\Requests\Vouchers\ConfirmVoucherPaymentRequest;
use LBHurtado\XChange\Services\ApiResponseFactory;
use LBHurtado\XChange\Services\VoucherAccessService;

class ConfirmVoucherPaymentController
{
    public function __invoke(
        string $code,
        ConfirmVoucherPaymentRequest $request,
        VoucherAccessService $vouchers,
        VoucherCollectionWalletResolverContract $wallets,
        CollectVoucherFunds $collect,
        ApiResponseFactory $responses,
    ) {
        $voucher = $vouchers->findByCodeOrFail($code);

        $result = $collect->handle(
            voucher: $voucher,
            wallet: $wallets->resolve($voucher),
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
