<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Execution;

use LBHurtado\EmiCore\Contracts\PayoutProvider;
use LBHurtado\EmiCore\Data\PayoutRequestData;
use LBHurtado\EmiCore\Data\PayoutResultData;
use LBHurtado\EmiCore\Enums\SettlementRail;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Voucher\Pipelines\RedeemedVoucher\DisburseCash;
use RuntimeException;

final readonly class FinalizeApprovedLegacyPayout
{
    public function __construct(private PayoutProvider $provider) {}

    public function handle(Voucher $voucher, PayoutRequestData $approved, PayoutResultData $result): void
    {
        if (filled(data_get($voucher->metadata, 'disbursement.cash_withdrawal_uuid'))) {
            return;
        }

        $voucher->redeemer = $voucher->redeemers()->first();
        $recorded = new class($this->provider, $approved, $result) implements PayoutProvider
        {
            private bool $used = false;

            public function __construct(private PayoutProvider $provider, private PayoutRequestData $approved, private PayoutResultData $result) {}

            public function disburse(PayoutRequestData $request): PayoutResultData
            {
                if ($this->used || (int) round($request->amount * 100) !== (int) round($this->approved->amount * 100)) {
                    throw new RuntimeException('Recorded payout does not match the approved amount.');
                }
                foreach (['reference', 'account_number', 'bank_code', 'settlement_rail', 'currency'] as $field) {
                    if (! hash_equals($this->approved->{$field}, $request->{$field})) {
                        throw new RuntimeException('Recorded payout does not match the approved destination.');
                    }
                }
                $this->used = true;

                return $this->result;
            }

            public function checkStatus(string $transactionId): PayoutResultData
            {
                return $this->provider->checkStatus($transactionId);
            }

            public function getRailFee(SettlementRail $rail): int
            {
                return $this->provider->getRailFee($rail);
            }
        };

        (new DisburseCash($recorded))->handle($voucher, fn (Voucher $processed): Voucher => $processed);
        if (! filled(data_get($voucher->fresh()?->metadata, 'disbursement.cash_withdrawal_uuid'))) {
            throw new RuntimeException('Approved payout wallet accounting requires review.');
        }
    }
}
