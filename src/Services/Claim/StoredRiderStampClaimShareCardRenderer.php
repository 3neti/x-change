<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Claim;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\ClaimShareCardRendererContract;
use LBHurtado\XChange\Contracts\RiderStampArtifactStoreContract;
use LBHurtado\XChange\Contracts\RiderStampClaimCardComposerContract;
use LBHurtado\XChange\Data\Claim\ClaimShareCardData;

final readonly class StoredRiderStampClaimShareCardRenderer implements ClaimShareCardRendererContract
{
    public function __construct(
        private RiderStampArtifactStoreContract $artifacts,
        private RiderStampClaimCardComposerContract $legacyComposer,
    ) {}

    public function render(
        Voucher $voucher,
        string $claimUrl,
    ): ClaimShareCardData {
        return $this->artifacts->read($voucher)
            ?? $this->legacyComposer->compose($voucher, $claimUrl);
    }
}
