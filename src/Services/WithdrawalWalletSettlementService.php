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
    public function settle(
        Voucher $voucher,
        PayoutRequestData $input,
        float $withdrawAmount,
        int $sliceNumber,
    ): WithdrawalWalletSettlementData {
        // TODO: Move rail fee resolution into a dedicated fee/rail service.
        // Provider call has already been extracted out of the processor.
        $feeAmount = 0;

        $feeStrategy = data_get($voucher->instructions, 'cash.fee_strategy', 'absorb');

//        $cash = null;
//
//        if ($voucher instanceof Wallet) {
//            $cash = $voucher;
//        } elseif ($voucher->relationLoaded('user')) {
//            $cash = $voucher->getRelation('user');
//        } elseif (method_exists($voucher, 'user')) {
//            $cash = $voucher->user()->first();
//        }
//
//        if (! $cash instanceof Wallet && auth()->user() instanceof Wallet) {
//            $cash = auth()->user();
//        }
//
//        if (! $cash instanceof Wallet) {
//            throw new RuntimeException('Voucher wallet owner is required for withdrawal settlement.');
//        }

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
