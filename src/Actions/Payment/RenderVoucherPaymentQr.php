<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Payment;

use LBHurtado\XChange\Contracts\VoucherPaymentQrRendererContract;
use LBHurtado\XChange\Data\Payment\RenderedVoucherPaymentQrData;
use LBHurtado\XChange\Data\Payment\VoucherPaymentQrData;
use Lorisleiva\Actions\Concerns\AsAction;

class RenderVoucherPaymentQr
{
    use AsAction;

    public function __construct(
        protected VoucherPaymentQrRendererContract $renderer,
    ) {}

    public function handle(VoucherPaymentQrData $qr): RenderedVoucherPaymentQrData
    {
        return $this->renderer->render($qr);
    }
}
