<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Settlement;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Actions\Payment\CollectVoucherFunds;
use LBHurtado\XChange\Data\Payment\VoucherPaymentResultData;

class CollectSettlementPayment
{
    public function __construct(
        protected CollectVoucherFunds $collectVoucherFunds,
    ) {}

    public function handle(Voucher $voucher, array $payload): VoucherPaymentResultData
    {
        return $this->collectVoucherFunds->handle($voucher, $payload);
    }
}
