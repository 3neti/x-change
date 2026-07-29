<?php

declare(strict_types=1);

namespace LBHurtado\XChange\ClaimWalkthrough;

use Illuminate\Contracts\Auth\Authenticatable;
use LBHurtado\Voucher\Data\VoucherInstructionsData;

final class ClaimPreviewVoucherPayloadFactory
{
    /**
     * @return array<string, mixed>
     */
    public function make(VoucherInstructionsData $instructions, Authenticatable $issuer): array
    {
        $payload = $instructions->toArray();

        data_set($payload, 'count', 1);
        data_set($payload, 'metadata.issuer_id', (string) $issuer->getAuthIdentifier());
        data_set($payload, 'metadata.walkthrough.preview', true);
        data_set($payload, 'metadata.walkthrough.purpose', 'claim-experience-preview');
        data_set($payload, 'metadata.walkthrough.money_movement', false);

        if (blank(data_get($payload, 'prefix'))) {
            data_set($payload, 'prefix', 'PV');
        }

        if (blank(data_get($payload, 'mask'))) {
            data_set($payload, 'mask', '****');
        }

        return $payload;
    }
}
