<?php

declare(strict_types=1);

namespace LBHurtado\XChange\ClaimWalkthrough;

use Illuminate\Contracts\Auth\Authenticatable;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\XChange\Contracts\RiderSplashArtworkSnapshotterContract;

final class ClaimPreviewVoucherPayloadFactory
{
    public function __construct(
        private readonly RiderSplashArtworkSnapshotterContract $splashArtwork,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function make(VoucherInstructionsData $instructions, Authenticatable $issuer): array
    {
        $payload = $instructions->toArray();

        data_set($payload, 'count', 1);
        data_set($payload, 'metadata.issuer_id', (string) $issuer->getAuthIdentifier());
        data_set($payload, 'metadata.custom.walkthrough.preview', true);
        data_set($payload, 'metadata.custom.walkthrough.purpose', 'claim-experience-preview');
        data_set($payload, 'metadata.custom.walkthrough.money_movement', false);

        if (blank(data_get($payload, 'prefix'))) {
            data_set($payload, 'prefix', 'PV');
        }

        if (blank(data_get($payload, 'mask'))) {
            data_set($payload, 'mask', '****');
        }

        return $this->splashArtwork->prepare($payload);
    }
}
