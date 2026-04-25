<?php

declare(strict_types=1);

use LBHurtado\Cash\Contracts\CashWithdrawalIntervalPolicyContract;
use LBHurtado\XChange\Adapters\VoucherWithdrawableInstrumentAdapter;
use LBHurtado\XChange\Models\VoucherClaim;
use LBHurtado\XChange\Services\XChangeWithdrawalIntervalEnforcer;

it('delegates interval decision to cash interval policy with last withdrawal timestamp', function () {
    config()->set('x-change.withdrawal.open_slice_min_interval_seconds', 60);

    $voucher = issueVoucher(validVoucherInstructions(
        amount: 100.00,
        settlementRail: 'INSTAPAY',
    ));

    VoucherClaim::query()->create([
        'voucher_id' => $voucher->id,
        'voucher_code' => $voucher->code,
        'claim_number' => 1,
        'claim_type' => 'withdraw',
        'status' => 'succeeded',
        'requested_amount_minor' => 5000,
        'disbursed_amount_minor' => 5000,
        'remaining_balance_minor' => 5000,
        'meta' => [],
        'created_at' => now()->subSeconds(30),
        'updated_at' => now()->subSeconds(30),
    ]);

    $policy = Mockery::mock(CashWithdrawalIntervalPolicyContract::class);

    $policy->shouldReceive('assertAllowed')
        ->once()
        ->withArgs(function ($instrument, $lastWithdrawalAt, $minimumIntervalSeconds) use ($voucher) {
            return $instrument instanceof VoucherWithdrawableInstrumentAdapter
                && $instrument->getInstrumentId() === $voucher->id
                && $lastWithdrawalAt instanceof DateTimeInterface
                && $minimumIntervalSeconds === 60;
        });

    $enforcer = new XChangeWithdrawalIntervalEnforcer($policy);

    $enforcer->enforce(new VoucherWithdrawableInstrumentAdapter($voucher), []);
});
