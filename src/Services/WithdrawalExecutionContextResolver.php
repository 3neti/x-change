<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Data\WithdrawalExecutionContextData;
use LBHurtado\XChange\Models\VoucherClaim;

class WithdrawalExecutionContextResolver
{
    public function resolve(Voucher $voucher, string $accountNumber): WithdrawalExecutionContextData
    {
        $claimNumber = ((int) VoucherClaim::query()
            ->where('voucher_id', $voucher->getKey())
            ->max('claim_number')) + 1;

        $sliceNumber = $claimNumber;

        $providerReference = sprintf(
            '%s-%s-S%d',
            $voucher->code,
            $accountNumber,
            $sliceNumber
        );

        return new WithdrawalExecutionContextData(
            claimNumber: $claimNumber,
            sliceNumber: $sliceNumber,
            providerReference: $providerReference,
        );
    }
}
