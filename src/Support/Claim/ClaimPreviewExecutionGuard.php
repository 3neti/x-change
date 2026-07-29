<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Support\Claim;

use LBHurtado\Voucher\Models\Voucher;
use RuntimeException;

final class ClaimPreviewExecutionGuard
{
    public function assertExecutable(Voucher $voucher): void
    {
        if (! $this->isPreview($voucher)) {
            return;
        }

        throw new RuntimeException('Claim experience preview Pay Codes cannot be executed.');
    }

    public function isPreview(Voucher $voucher): bool
    {
        return (bool) data_get(
            $voucher->metadata ?? [],
            'instructions.metadata.custom.walkthrough.preview',
            false,
        );
    }
}
