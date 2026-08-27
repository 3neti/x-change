<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Data\Payment\VoucherCollectionProgressData;
use LBHurtado\XChange\Enums\PaymentAttemptStatus;
use LBHurtado\XChange\Models\PaymentAttempt;

class VoucherConsumerStatusResolver
{
    public function __construct(
        private readonly VoucherCollectionProgressService $progress,
    ) {}

    public function resolve(Voucher $voucher): string
    {
        return $this->resolveFromProgress($voucher, $this->progress->compute($voucher));
    }

    public function resolveFromProgress(
        Voucher $voucher,
        VoucherCollectionProgressData $progress,
    ): string {
        if ($voucher->isCancelled()) {
            return 'cancelled';
        }

        if ($voucher->isExpired()) {
            return 'expired';
        }

        if ($progress->is_fully_collected) {
            return 'paid';
        }

        $latestAttempt = PaymentAttempt::query()
            ->whereBelongsTo($voucher)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first(['id', 'status']);

        if (in_array($latestAttempt?->status, [
            PaymentAttemptStatus::AwaitingPayment,
            PaymentAttemptStatus::Verifying,
            PaymentAttemptStatus::Verified,
        ], true)) {
            return 'processing';
        }

        return 'payable';
    }
}
