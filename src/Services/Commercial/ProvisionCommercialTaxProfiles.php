<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Commercial;

use LBHurtado\XChange\Models\CommercialTaxProfile;

final readonly class ProvisionCommercialTaxProfiles
{
    public function __construct(
        private CommercialTaxProfileRegistry $registry,
        private PersistCommercialTaxProfile $persist,
    ) {}

    /** @return list<CommercialTaxProfile> */
    public function provision(): array
    {
        $profiles = [];

        foreach (array_keys((array) config('x-change.commercial.tax_profiles.profiles', [])) as $reference) {
            $profiles[] = $this->persist->execute($this->registry->resolve((string) $reference));
        }

        return $profiles;
    }
}
