<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Payment;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\VoucherPaymentQrGeneratorContract;
use LBHurtado\XChange\Data\Payment\VoucherPaymentQrData;
use Lorisleiva\Actions\Concerns\AsAction;

class GenerateVoucherPaymentQr
{
    use AsAction;

    public function __construct(
        protected VoucherPaymentQrGeneratorContract $generator,
    ) {}

    public function handle(Voucher $voucher): VoucherPaymentQrData
    {
        return $this->generator->generate($voucher);
    }
}
