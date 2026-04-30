<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use Bavix\Wallet\Interfaces\Wallet;
use LBHurtado\EmiCore\Data\PayoutRequestData;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Wallet\Actions\WithdrawCash;
use LBHurtado\XChange\Data\WithdrawalWalletSettlementData;
use RuntimeException;

class WithdrawalWalletSettlementService
{
    public function __construct(
        protected VoucherCapabilityGuard $guard,
    ) {}

    public function settle(
        Voucher $voucher,
        PayoutRequestData $input,
        float $withdrawAmount,
        int $sliceNumber,
    ): WithdrawalWalletSettlementData {
        $this->guard->ensureCanDisburse($voucher);

        // TODO: Move rail fee resolution into a dedicated fee/rail service.
        // Provider call has already been extracted out of the processor.
        $feeAmount = 0;

        $feeStrategy = data_get($voucher->instructions, 'cash.fee_strategy', 'absorb');

        $cash = $this->resolveCashWallet($voucher);

        $transfer = WithdrawCash::run(
            $cash,
            $input->reference,
            'Voucher withdrawal',
            [
                'slice_number' => $sliceNumber,
                'voucher_code' => $voucher->code,
                'bank_code' => $input->bank_code,
                'account_number' => $input->account_number,
                'settlement_rail' => $input->settlement_rail,
                'fee_amount' => $feeAmount,
                'fee_strategy' => $feeStrategy,
            ],
            (int) round($withdrawAmount * 100),
        );

        return new WithdrawalWalletSettlementData(
            transfer: $transfer,
            feeAmount: $feeAmount,
            feeStrategy: $feeStrategy,
        );
    }

    protected function resolveCashWallet(Voucher $voucher): Wallet
    {
        if ($voucher instanceof Wallet) {
            return $voucher;
        }

        $cash = $voucher->cash ?? null;

        if ($cash instanceof Wallet) {
            return $cash;
        }

        if ($voucher->relationLoaded('cash') && $voucher->getRelation('cash') instanceof Wallet) {
            return $voucher->getRelation('cash');
        }

        if (method_exists($voucher, 'cash')) {
            $cash = $voucher->cash()->first();

            if ($cash instanceof Wallet) {
                return $cash;
            }
        }

        if ($voucher->relationLoaded('user') && $voucher->getRelation('user') instanceof Wallet) {
            return $voucher->getRelation('user');
        }

        if (method_exists($voucher, 'user')) {
            $user = $voucher->user()->first();

            if ($user instanceof Wallet) {
                return $user;
            }
        }

        throw new RuntimeException('Voucher wallet owner is required for withdrawal settlement.');
    }
}
