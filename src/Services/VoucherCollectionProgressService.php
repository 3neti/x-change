<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use Illuminate\Support\Arr;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Data\Payment\VoucherCollectionProgressData;
use LBHurtado\XChange\Models\VoucherCollection;

class VoucherCollectionProgressService
{
    public function compute(Voucher $voucher): VoucherCollectionProgressData
    {
        $target = $this->resolveTargetAmountMinor($voucher);

        $collected = (int) VoucherCollection::query()
            ->where('voucher_id', $voucher->getKey())
            ->whereIn('status', ['collected', 'succeeded'])
            ->sum('collected_amount_minor');

        $remaining = max($target - $collected, 0);
        $overpaid = max($collected - $target, 0);

        return new VoucherCollectionProgressData(
            currency: (string) data_get($voucher->metadata, 'instructions.cash.currency', 'PHP'),
            target_amount_minor: $target,
            collected_total_minor: $collected,
            remaining_to_collect_minor: $remaining,
            is_fully_collected: $target > 0 && $collected >= $target,
            is_overpaid: $overpaid > 0,
            overpaid_amount_minor: $overpaid,
        );
    }

    public function persistSummary(Voucher $voucher): VoucherCollectionProgressData
    {
        $progress = $this->compute($voucher);

        $metadata = (array) $voucher->getAttribute('metadata');

        data_set($metadata, 'collection_progress', $progress->toArray());

        if (
            $progress->is_fully_collected
            && config('x-change.payment.auto_close_collectible_vouchers', false)
        ) {
            data_set($metadata, 'collection.closed_at', now()->toISOString());
            data_set($metadata, 'collection.closed_reason', 'target_amount_collected');
        }

        $voucher->forceFill([
            'metadata' => $metadata,
        ])->save();

        return $progress;
    }

    protected function resolveTargetAmountMinor(Voucher $voucher): int
    {
        $meta = (array) $voucher->getAttribute('metadata');

        $target = Arr::get($meta, 'instructions.target_amount')
            ?? Arr::get($meta, 'target_amount')
            ?? 0;

        return (int) round(((float) $target) * 100);
    }
}
