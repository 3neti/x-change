<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Claim;

use Illuminate\Support\Collection;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Models\StoredValueHolderBinding;
use LBHurtado\XChange\Models\VoucherClaim;

final class ClaimSurfaceContextData
{
    private ?bool $storedValueActivationRetry = null;

    /**
     * @param  Collection<int, VoucherClaim>  $claims  Ordered latest-first, with evidence eager loaded.
     * @param  array<string, mixed>  $voucherSummary  The `VoucherLifecycleServiceContract::showByCode()` array -- the
     *                                                same shape `InspectPayCodeXRayController`/`VoucherXRayProjectionBuilder`
     *                                                already consume, reused here instead of re-deriving it.
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
        return $this->claims->isNotEmpty() && ! $this->isStoredValueActivationRetry();
    }

    public function isStoredValueActivationRetry(): bool
    {
        if ($this->storedValueActivationRetry !== null) {
            return $this->storedValueActivationRetry;
        }

        if (data_get($this->voucher->metadata, 'instructions.execution.driver') !== 'stored_value'
            || $this->claims->isEmpty()
            || ! $this->claims->every(static fn (VoucherClaim $claim): bool => $claim->status === 'execution_failed')) {
            return $this->storedValueActivationRetry = false;
        }

        return $this->storedValueActivationRetry = ! StoredValueHolderBinding::query()
            ->where('voucher_id', $this->voucher->getKey())
            ->where('status', 'active')
            ->exists();
    }
}
