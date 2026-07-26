<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Funding;

use LBHurtado\Voucher\Enums\VoucherType;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Models\FundingRequest;
use RuntimeException;

final class ReviewedFundingRequestLocator
{
    public function byPayCode(string $payCode): FundingRequest
    {
        $normalized = mb_strtoupper(trim($payCode));
        $voucher = Voucher::query()
            ->where('code', $normalized)
            ->first();

        if (! $voucher instanceof Voucher) {
            throw new RuntimeException(
                "Reviewed Account Funding Pay Code [{$normalized}] was not found.",
            );
        }

        if ($voucher->voucher_type !== VoucherType::PAYABLE) {
            throw new RuntimeException(
                "Pay Code [{$normalized}] is not a reviewed Account Funding PAYABLE.",
            );
        }

        $request = FundingRequest::query()
            ->with(['voucher.envelope', 'voucher.owner', 'events'])
            ->where('voucher_id', $voucher->getKey())
            ->first();

        if (! $request instanceof FundingRequest) {
            throw new RuntimeException(
                "Pay Code [{$normalized}] has no reviewed Funding Request.",
            );
        }

        return $request;
    }
}
