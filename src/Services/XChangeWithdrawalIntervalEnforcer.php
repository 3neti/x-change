<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\Cash\Contracts\WithdrawableInstrumentContract;
use LBHurtado\Cash\Contracts\WithdrawalIntervalEnforcerContract;
use LBHurtado\XChange\Models\VoucherClaim;

use RuntimeException;

class XChangeWithdrawalIntervalEnforcer implements WithdrawalIntervalEnforcerContract

{
    public function enforce(WithdrawableInstrumentContract $instrument, array $payload): void
    {
        $minInterval = (int) config('x-change.withdrawal.open_slice_min_interval_seconds', 0);

        if ($minInterval <= 0) {
            return;
        }

        $currentAccountNumber = (string) data_get($payload, 'bank_account.account_number', '');

        if ($currentAccountNumber === '') {
            return;
        }

        $instrumentId = $instrument->getInstrumentId();

        if ($instrumentId === null) {
            return;
        }

        $lastWithdrawClaim = VoucherClaim::query()
            ->where('voucher_id', $instrumentId)
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
