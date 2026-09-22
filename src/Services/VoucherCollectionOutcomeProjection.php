<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Data\Payment\VoucherCollectionProgressData;

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

        return [
            'outcome' => $outcome,
            'availability' => $availability,
            'next_action' => $outcome === 'paid' ? 'view_receipt' : ($availability === 'open' ? 'pay' : 'none'),
        ];
    }
}
