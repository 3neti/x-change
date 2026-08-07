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
