<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Data\Claim\ClaimShareCardData;
use LBHurtado\XChange\Data\Claim\RiderStampArtifactData;

interface RiderStampArtifactStoreContract
{
    public function materialize(
        Voucher $voucher,
        string $claimUrl,
        bool $force = false,
    ): RiderStampArtifactData;

    public function descriptor(Voucher $voucher): ?RiderStampArtifactData;

    public function read(Voucher $voucher): ?ClaimShareCardData;
}
