<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\EmiCore\Enums\SettlementRail;
use LBHurtado\Voucher\Models\Voucher;

final class SettlementRailResolver
{
    public function forVoucher(Voucher $voucher, float $payoutAmount): SettlementRail
    {
        $instruction = data_get($voucher->instructions, 'cash.settlement_rail');

        if ($instruction instanceof SettlementRail) {
            return $instruction;
        }

        if (is_string($instruction)) {
            $explicit = SettlementRail::tryFrom(strtoupper(trim($instruction)));

            if ($explicit !== null) {
                return $explicit;
            }
        }

        return $this->automatic($payoutAmount);
    }

    public function automatic(float $payoutAmount): SettlementRail
    {
        $thresholdMinor = max(1, (int) config('x-change.payout.automatic_rail_threshold_minor', 5_000_000));

        return (int) round($payoutAmount * 100) < $thresholdMinor
            ? SettlementRail::INSTAPAY
            : SettlementRail::PESONET;
    }
}
