<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Data\Claim\RiderSplashArtworkSnapshotData;
use LBHurtado\XChange\Exceptions\RiderStampArtworkUnavailable;

interface RiderSplashArtworkSnapshotterContract
{
    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     *
     * @throws RiderStampArtworkUnavailable when Splash artwork is selected but cannot be secured
     */
    public function prepare(array $input): array;

    public function capture(
        Voucher $voucher,
        bool $force = false,
    ): ?RiderSplashArtworkSnapshotData;

    public function dataUrl(Voucher $voucher): ?string;
}
