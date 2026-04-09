<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\WithdrawalExecutionContract;
use LBHurtado\XChange\Contracts\WithdrawalProcessorContract;
use LBHurtado\XChange\Contracts\WithdrawalValidationContract;
use LBHurtado\XChange\Data\Redemption\WithdrawPayCodeResultData;

class DefaultWithdrawalExecutionService implements WithdrawalExecutionContract
{
    public function __construct(
        protected WithdrawalValidationContract $validator,
        protected WithdrawalProcessorContract $processor,
    ) {}

    public function withdraw(Voucher $voucher, array $payload): WithdrawPayCodeResultData
    {
        $this->validator->validate($voucher, $payload);

        return $this->processor->process($voucher, $payload);
    }
}
