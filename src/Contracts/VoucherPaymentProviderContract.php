<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Data\Payment\VoucherPaymentResultData;

interface VoucherPaymentProviderContract
{
    public function confirm(Voucher $voucher, array $payload): VoucherPaymentResultData;
}
