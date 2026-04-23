<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use InvalidArgumentException;
use LBHurtado\Voucher\Enums\VoucherState;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\WithdrawalValidationContract;
use LBHurtado\XChange\Models\VoucherClaim;
use RuntimeException;

class DefaultWithdrawalValidationService implements WithdrawalValidationContract
{
    public function validate(Voucher $voucher, array $payload): void
    {
        if ($this->isOpenSliceVoucher($voucher)) {
            $this->validateOpenSliceVoucher($voucher, $payload);

            return;
        }

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

    protected function isOpenSliceVoucher(Voucher $voucher): bool
    {
        return method_exists($voucher, 'isDivisible')
            && $voucher->isDivisible()
            && method_exists($voucher, 'getSliceMode')
            && $voucher->getSliceMode() === 'open';
    }

    protected function validateOpenSliceVoucher(Voucher $voucher, array $payload): void
    {
        $state = $voucher->state instanceof VoucherState
            ? $voucher->state->value
            : (string) $voucher->state;

        if ($state !== 'active') {
            throw new RuntimeException('This voucher is not withdrawable.');
        }

        if (method_exists($voucher, 'isExpired') && $voucher->isExpired()) {
            throw new RuntimeException('This voucher has expired.');
        }

        $amount = data_get($payload, 'amount');

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

        if (method_exists($voucher, 'getMaxSlices') && method_exists($voucher, 'getConsumedSlices')) {
            $maxSlices = $voucher->getMaxSlices();

            if ($maxSlices !== null && $voucher->getConsumedSlices() >= $maxSlices) {
                throw new RuntimeException('This voucher has no remaining slices.');
            }
        }

        $this->validateOpenSliceClaimInterval($voucher, $payload);
    }

    protected function validateOpenSliceClaimInterval(Voucher $voucher, array $payload): void
    {
        $minInterval = (int) config('x-change.withdrawal.open_slice_min_interval_seconds', 0);

        if ($minInterval <= 0) {
            return;
        }

        $currentAccountNumber = (string) data_get($payload, 'bank_account.account_number', '');

        if ($currentAccountNumber === '') {
            return;
        }

        $lastWithdrawClaim = VoucherClaim::query()
            ->where('voucher_id', $voucher->getKey())
            ->where('claim_type', 'withdraw')
            ->latest('claim_number')
            ->latest('id')
            ->first();

        if (! $lastWithdrawClaim) {
            return;
        }

        $previousAccountNumber = (string) data_get($lastWithdrawClaim->meta, 'disbursement.account_number', '');

        if ($previousAccountNumber === '' || $previousAccountNumber !== $currentAccountNumber) {
            return;
        }

        $lastAttemptedAt = $lastWithdrawClaim->attempted_at;

        if (! $lastAttemptedAt) {
            return;
        }

        $elapsed = (float) $lastAttemptedAt->diffInRealSeconds(now(), true);

        if ($elapsed < $minInterval) {
            $remaining = (int) ceil($minInterval - $elapsed);

            throw new RuntimeException(
                "Please wait {$remaining} more second(s) before sending another withdrawal to the same destination account."
            );
        }
    }
}
