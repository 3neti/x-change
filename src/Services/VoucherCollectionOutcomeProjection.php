<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Data\Payment\VoucherCollectionProgressData;
use LBHurtado\XChange\Models\DisbursementReconciliation;
use LBHurtado\XChange\Models\VoucherClaim;

/**
 * Collection outcome is historical; voucher availability is a separate fact.
 */
class VoucherCollectionOutcomeProjection
{
    /** @return array{outcome: string, availability: string, next_action: string} */
    public function project(Voucher $voucher, VoucherCollectionProgressData $progress): array
    {
        $availability = match (true) {
            $voucher->isCancelled() => 'cancelled',
            $voucher->isExpired() => 'expired',
            $progress->is_fully_collected => 'closed',
            $voucher->isClosed() => 'closed',
            $voucher->starts_at?->isFuture() === true => 'scheduled',
            default => 'open',
        };

        $outcome = $progress->is_fully_collected ? 'paid' : 'unpaid';
        $awaitingDisbursement = $outcome !== 'paid'
            && SettlementRepaymentPolicy::forVoucher($voucher)
            && ! SettlementRepaymentPolicy::disbursed($voucher);
        $claimStarted = $awaitingDisbursement && ($voucher->isRedeemed()
            || DisbursementReconciliation::query()->where('voucher_id', $voucher->getKey())
                ->where('claim_type', '!=', 'value_flow')->exists()
            || VoucherClaim::query()->where('voucher_id', $voucher->getKey())
                ->whereIn('claim_type', ['withdraw', 'disbursement'])->exists());

        $nextAction = match (true) {
            $outcome === 'paid' => 'view_receipt',
            $availability !== 'open' => 'none',
            $awaitingDisbursement => $claimStarted ? 'none' : 'claim',
            default => 'pay',
        };

        return [
            'outcome' => $outcome,
            'availability' => $availability,
            'next_action' => $nextAction,
        ];
    }
}
