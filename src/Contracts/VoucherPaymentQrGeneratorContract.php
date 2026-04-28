<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Data\Payment\VoucherPaymentQrData;

interface VoucherPaymentQrGeneratorContract
{
    public function generate(Voucher $voucher): VoucherPaymentQrData;
}
