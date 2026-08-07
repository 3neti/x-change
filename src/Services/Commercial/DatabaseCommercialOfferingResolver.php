<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Commercial;

use Illuminate\Support\Facades\Schema;
use LBHurtado\XChange\Contracts\CommercialOfferingResolverContract;
use LBHurtado\XChange\Enums\CommercialOfferingStatus;
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
        }

        if (! (bool) config('x-change.commercial.offerings.use_published', false)
            || ! Schema::hasTable('x_change_commercial_offerings')) {
            return $this->bootstrap->make($profile);
        }

        $offering = CommercialOffering::query()
            ->where('profile', $profile)
            ->where('status', CommercialOfferingStatus::Published->value)
            ->where('effective_at', '<=', now())
            ->latest('effective_at')
            ->latest('version')
            ->first();

        return $offering?->offering() ?? $this->bootstrap->make($profile);
    }
}
