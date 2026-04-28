<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\XChange\Data\Payment\RenderedVoucherPaymentQrData;
use LBHurtado\XChange\Data\Payment\VoucherPaymentQrData;

interface VoucherPaymentQrRendererContract
{
    public function render(VoucherPaymentQrData $qr): RenderedVoucherPaymentQrData;
}
