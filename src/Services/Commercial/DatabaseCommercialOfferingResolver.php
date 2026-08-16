<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Commercial;

use Illuminate\Support\Facades\Schema;
use LBHurtado\XChange\Contracts\CommercialOfferingResolverContract;
use LBHurtado\XChange\Models\CommercialOffering;
use LBHurtado\XChange\Models\CommercialOfferingActivation;
use LBHurtado\XCommerce\Data\CommercialOfferingData;

final class DatabaseCommercialOfferingResolver implements CommercialOfferingResolverContract
{
    public function __construct(
        private readonly BootstrapCommercialOfferingFactory $bootstrap,
        private readonly CommercialOfferingManifestCompiler $manifests,
    ) {}

    public function resolve(string $profile): CommercialOfferingData
    {
        if (Schema::hasTable('x_change_commercial_offering_activations')) {
            $activation = CommercialOfferingActivation::query()
                ->with('offering')
                ->where('profile', $profile)
                ->whereNull('deactivated_at')
                ->latest('activated_at')
                ->first();

            if ($activation instanceof CommercialOfferingActivation) {
                $offering = $activation->offering;

                if (! $offering instanceof CommercialOffering
                    || $offering->snapshot_hash !== $activation->snapshot_hash
                    || $offering->snapshot_hash !== $offering->offering()->snapshotHash()) {
                    throw new \DomainException('Active Commercial Offering evidence is inconsistent.');
                }

                if ($offering->manifest_hash !== null || $offering->manifest_yaml !== null) {
                    if ($offering->manifest_schema !== CommercialOfferingManifestCompiler::Schema
                        || ! is_string($offering->manifest_hash)
                        || ! is_string($offering->manifest_yaml)) {
                        throw new \DomainException('Active Commercial Offering manifest evidence is incomplete.');
                    }

                    $manifest = $this->manifests->parse($offering->manifest_yaml);

                    if ($manifest->profile !== $profile
                        || $manifest->hash !== $offering->manifest_hash
                        || $manifest->offering->snapshotHash() !== $offering->snapshot_hash) {
                        throw new \DomainException('Active Commercial Offering manifest evidence is inconsistent.');
                    }
                }

                return $offering->offering();
            }

            if (in_array(
                $profile,
                (array) config('x-change.commercial.offerings.profiles', []),
                true,
            )) {
                throw new \DomainException("Commercial Offering profile [{$profile}] has no active governed version.");
            }
        }

        return $this->bootstrap->make($profile);
    }
}
