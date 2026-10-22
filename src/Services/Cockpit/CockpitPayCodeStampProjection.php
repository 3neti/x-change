<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Cockpit;

use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\XChange\Contracts\RiderStampArtifactStoreContract;

final readonly class CockpitPayCodeStampProjection
{
    public function __construct(private RiderStampArtifactStoreContract $artifacts) {}

    /** @return array{available: bool, thumbnail_url?: string, image_url?: string, download_url?: string, detail_url?: string} */
    public function forVoucher(Voucher $voucher): array
    {
        $artifact = $this->artifacts->descriptor($voucher);

        if ($artifact === null) {
            return ['available' => false];
        }

        $parameters = ['code' => $voucher->code, 'sha256' => $artifact->sha256];
        $imageUrl = route('x-change.cockpit.pay-codes.stamp.show', [...$parameters, 'size' => 'full']);

        return [
            'available' => true,
            'thumbnail_url' => route('x-change.cockpit.pay-codes.stamp.show', [...$parameters, 'size' => 'thumbnail']),
            'image_url' => $imageUrl,
            'download_url' => $imageUrl.'?download=1',
            'detail_url' => route('x-change.cockpit.pay-codes.show', ['code' => $voucher->code, 'tab' => 'stamp']),
        ];
    }
}
