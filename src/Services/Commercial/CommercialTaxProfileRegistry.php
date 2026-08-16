<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Commercial;

use LBHurtado\XChange\Exceptions\CommercialSaleConflict;
use LBHurtado\XChange\Models\CommercialTaxProfile;
use LBHurtado\XCommerce\Data\CommercialTaxProfileData;
use Throwable;

final class CommercialTaxProfileRegistry
{
    public function resolve(string $reference): CommercialTaxProfileData
    {
        $profile = config('x-change.commercial.tax_profiles.profiles.'.trim($reference));

        if (! is_array($profile)) {
            throw new CommercialSaleConflict("Commercial Tax Profile [{$reference}] is not governed or active.");
        }

        try {
            return CommercialTaxProfileData::fromArray([
                'reference' => trim($reference),
                ...$profile,
            ]);
        } catch (Throwable $exception) {
            throw new CommercialSaleConflict(
                "Commercial Tax Profile [{$reference}] is malformed.",
                previous: $exception,
            );
        }
    }

    public function resolveEffective(string $reference): CommercialTaxProfileData
    {
        $configured = $this->resolve($reference);
        $profiles = CommercialTaxProfile::query()
            ->currentlyEffective()
            ->where('reference', trim($reference))
            ->get();

        if ($profiles->count() !== 1) {
            throw new CommercialSaleConflict(
                "Commercial Tax Profile [{$reference}] does not have exactly one effective governed version.",
            );
        }

        $persisted = $profiles->sole();
        $profile = CommercialTaxProfileData::fromArray((array) $persisted->snapshot);

        if (! hash_equals($persisted->snapshot_hash, $profile->snapshotHash())
            || ! hash_equals($configured->snapshotHash(), $profile->snapshotHash())) {
            throw new CommercialSaleConflict(
                "Commercial Tax Profile [{$reference}] evidence does not match the active deployment authority.",
            );
        }

        return $profile;
    }
}
