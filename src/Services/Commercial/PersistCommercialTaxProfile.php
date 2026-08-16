<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Commercial;

use LBHurtado\XChange\Exceptions\CommercialSaleConflict;
use LBHurtado\XChange\Models\CommercialTaxProfile;
use LBHurtado\XCommerce\Data\CommercialTaxProfileData;

final class PersistCommercialTaxProfile
{
    public function execute(CommercialTaxProfileData $profile): CommercialTaxProfile
    {
        $persisted = CommercialTaxProfile::query()->firstOrCreate([
            'reference' => trim($profile->reference),
            'version' => $profile->version,
        ], [
            ...$profile->toArray(),
            'snapshot_hash' => $profile->snapshotHash(),
            'snapshot' => $profile->toArray(),
        ]);

        if (! hash_equals($persisted->snapshot_hash, $profile->snapshotHash())) {
            throw new CommercialSaleConflict(
                "Commercial Tax Profile [{$profile->reference}] version [{$profile->version}] changed without a new version.",
            );
        }

        return $persisted;
    }
}
