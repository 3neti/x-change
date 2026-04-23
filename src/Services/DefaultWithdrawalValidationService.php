<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\Cash\Contracts\CashWithdrawalValidationContract;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Adapters\VoucherWithdrawableInstrumentAdapter;
use LBHurtado\XChange\Contracts\WithdrawalValidationContract;

class DefaultWithdrawalValidationService implements WithdrawalValidationContract
{
    public function __construct(
        protected CashWithdrawalValidationContract $validator,
    ) {}

    public function validate(Voucher $voucher, array $payload): void
    {
        $instrument = new VoucherWithdrawableInstrumentAdapter($voucher);

        $this->validator->validate($instrument, $payload);
    }
}
