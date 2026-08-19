<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Data\WithdrawalExecutionContextData;
use LBHurtado\XChange\Models\VoucherClaim;

class WithdrawalExecutionContextResolver
{
    /** @param array<string, mixed> $payload */
    public function resolve(Voucher $voucher, string $accountNumber, array $payload = []): WithdrawalExecutionContextData
    {
        $executionReference = data_get($payload, '_slice_execution.reference');

        if (is_string($executionReference) && $executionReference !== '') {
            $claimNumber = (int) data_get($payload, '_slice_execution.claim_number');
            $providerReference = (string) data_get($payload, '_slice_execution.provider_operation_reference');

            if ($claimNumber > 0 && $providerReference !== '') {
                return new WithdrawalExecutionContextData(
                    claimNumber: $claimNumber,
                    sliceNumber: $claimNumber,
                    providerReference: $providerReference,
                );
            }
        }

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
