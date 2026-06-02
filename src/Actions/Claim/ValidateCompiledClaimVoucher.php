<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Claim;

use LBHurtado\Voucher\Models\Voucher;

final class ValidateCompiledClaimVoucher
{
    public function handle(?Voucher $voucher): ?string
    {
        if (! $voucher) {
            return 'Invalid Pay Code.';
        }

        if ($voucher->isRedeemed()) {
            return 'This Pay Code has already been redeemed.';
        }

        if ($voucher->isExpired()) {
            return 'This Pay Code has expired.';
        }

        if (! $voucher->canRedeem()) {
            return 'This Pay Code cannot be redeemed.';
        }

        return null;
    }
}
