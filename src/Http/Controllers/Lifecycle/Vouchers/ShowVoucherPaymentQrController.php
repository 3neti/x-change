<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Lifecycle\Vouchers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Actions\Payment\GenerateVoucherPaymentQr;
use LBHurtado\XChange\Actions\Payment\RenderVoucherPaymentQr;
use LBHurtado\XChange\Services\ApiResponseFactory;
use LBHurtado\XChange\Services\VoucherAccessService;

class ShowVoucherPaymentQrController extends Controller
{
    public function __invoke(
        string $code,
        VoucherAccessService $vouchers,
        GenerateVoucherPaymentQr $generate,
        RenderVoucherPaymentQr $render,
        ApiResponseFactory $responses,
    ): JsonResponse {
        $voucher = $vouchers->findByCodeOrFail($code);

        $qr = $generate->handle($voucher);
        $rendered = $render->handle($qr);

        return $responses->success($rendered);
    }
}
