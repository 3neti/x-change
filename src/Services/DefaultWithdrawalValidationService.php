<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use InvalidArgumentException;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\WithdrawalValidationContract;
use RuntimeException;

class DefaultWithdrawalValidationService implements WithdrawalValidationContract
{
    public function validate(Voucher $voucher, array $payload): void
    {
        if (! method_exists($voucher, 'canWithdraw') || ! $voucher->canWithdraw()) {
            throw new RuntimeException('This voucher is not withdrawable.');
        }

        $sliceMode = method_exists($voucher, 'getSliceMode')
            ? $voucher->getSliceMode()
            : null;

        $amount = data_get($payload, 'amount');

        if ($sliceMode === 'open') {
            if ($amount === null || $amount === '') {
                throw new InvalidArgumentException('Withdrawal amount is required.');
            }

            if (! is_numeric($amount)) {
                throw new InvalidArgumentException('Withdrawal amount must be numeric.');
            }

            $amount = (float) $amount;

            if ($amount <= 0) {
                throw new InvalidArgumentException('Withdrawal amount must be greater than zero.');
            }

            if (method_exists($voucher, 'getRemainingBalance')) {
                $remaining = (float) $voucher->getRemainingBalance();

                if ($amount > $remaining) {
                    throw new InvalidArgumentException('Withdrawal amount exceeds remaining balance.');
                }
            }

            if (method_exists($voucher, 'getMinWithdrawal')) {
                $minWithdrawal = $voucher->getMinWithdrawal();

                if ($minWithdrawal !== null && $amount < (float) $minWithdrawal) {
                    throw new InvalidArgumentException('Withdrawal amount is below the minimum withdrawal amount.');
                }
            }
        }
    }
}
