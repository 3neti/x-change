<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Claim;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Services\NamedVoucherSliceService;

final class ValidateCompiledClaimVoucher
{
    public function __construct(
        protected NamedVoucherSliceService $namedSlices,
    ) {}

    public function handle(?Voucher $voucher): ?string
    {
        if (! $voucher) {
            return 'Invalid Pay Code.';
        }

        if ($this->namedSlices->hasNamedSlices($voucher)) {
            if ($this->namedSlices->allSlicesClaimed($voucher)) {
                return 'This Pay Code has already been redeemed.';
            }

            if ($voucher->isExpired()) {
                return 'This Pay Code has expired.';
            }

            if ($voucher->redeemed_at === null && ! $voucher->canRedeem()) {
                return 'This Pay Code cannot be redeemed.';
            }

            return null;
        }

        if ($voucher->isRedeemed()) {
            return 'This Pay Code has already been redeemed.';
        }

        if ($voucher->isExpired()) {
            return 'This Pay Code has expired.';
        }

        if ($this->isExhaustedDivisibleVoucher($voucher)) {
            return 'This Pay Code has already been redeemed.';
        }

        if (! $voucher->canRedeem()) {
            return 'This Pay Code cannot be redeemed.';
        }

        return null;
    }

    protected function isExhaustedDivisibleVoucher(Voucher $voucher): bool
    {
        if (! method_exists($voucher, 'isDivisible') || ! $voucher->isDivisible()) {
            return false;
        }

        $remainingBalance = $this->safeCall($voucher, 'getRemainingBalance');

        if (is_numeric($remainingBalance) && (float) $remainingBalance <= 0.0) {
            return true;
        }

        $remainingSlices = $this->safeCall($voucher, 'getRemainingSlices');

        return is_numeric($remainingSlices) && (int) $remainingSlices <= 0;
    }

    protected function safeCall(Voucher $voucher, string $method): mixed
    {
        if (! method_exists($voucher, $method)) {
            return null;
        }

        try {
            return $voucher->{$method}();
        } catch (\Throwable) {
            return null;
        }
    }
}
