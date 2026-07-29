<?php

declare(strict_types=1);

namespace LBHurtado\XChange\ClaimWalkthrough;

use LBHurtado\Voucher\Models\Voucher;

final class ClaimPreviewVoucherDisposer
{
    public function dispose(int|string|null $voucherId): void
    {
        if ($voucherId === null || $voucherId === '') {
            return;
        }

        $voucher = Voucher::query()->find($voucherId);

        if (
            ! $voucher instanceof Voucher
            || ! (bool) data_get(
                $voucher->metadata ?? [],
                'instructions.metadata.custom.walkthrough.preview',
                false,
            )
        ) {
            return;
        }

        Voucher::query()->whereKey($voucher->getKey())->delete();
    }
}
