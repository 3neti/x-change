<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use Carbon\Carbon;
use LBHurtado\Cash\Contracts\CashWithdrawalIntervalPolicyContract;
use LBHurtado\Cash\Contracts\WithdrawableInstrumentContract;
use LBHurtado\Cash\Contracts\WithdrawalIntervalEnforcerContract;
use LBHurtado\XChange\Models\VoucherClaim;

class XChangeWithdrawalIntervalEnforcer implements WithdrawalIntervalEnforcerContract
{
    public function __construct(
        protected CashWithdrawalIntervalPolicyContract $intervalPolicy,
    ) {}

    public function enforce(WithdrawableInstrumentContract $instrument, array $payload): void
    {
        $lastWithdrawalAt = VoucherClaim::query()
            ->where('voucher_id', $instrument->getInstrumentId())
            ->where('claim_type', 'withdraw')
            ->where('status', 'succeeded')
            ->latest('created_at')
            ->value('created_at');

        $this->intervalPolicy->assertAllowed(
            instrument: $instrument,
            lastWithdrawalAt: $lastWithdrawalAt instanceof \DateTimeInterface
                ? $lastWithdrawalAt
                : ($lastWithdrawalAt ? Carbon::parse($lastWithdrawalAt) : null),
            minimumIntervalSeconds: (int) config('x-change.withdrawal.open_slice_min_interval_seconds', 0),
        );
    }
}
