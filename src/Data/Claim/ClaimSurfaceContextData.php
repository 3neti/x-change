<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Claim;

use Illuminate\Support\Collection;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Models\VoucherClaim;

final class ClaimSurfaceContextData
{
    /**
     * @param  Collection<int, VoucherClaim>  $claims  Ordered latest-first, with evidence eager loaded.
     * @param  array<string, mixed>  $voucherSummary  The `VoucherLifecycleServiceContract::showByCode()` array -- the
     *                                                 same shape `InspectPayCodeXRayController`/`VoucherXRayProjectionBuilder`
     *                                                 already consume, reused here instead of re-deriving it.
     */
    public function __construct(
        public readonly string $code,
        public readonly Voucher $voucher,
        public readonly ClaimSurfaceViewerData $viewer,
        public readonly ClaimSurfaceStateData $state,
        public readonly Collection $claims,
        public readonly bool $approvalRequired,
        public readonly array $voucherSummary = [],
        public readonly ?string $approvalActionUrl = null,
    ) {}

    public function latestClaim(): ?VoucherClaim
    {
        return $this->claims->first();
    }

    public function hasClaimActivity(): bool
    {
        return $this->claims->isNotEmpty();
    }
}
