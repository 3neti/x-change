<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Commercial;

use Illuminate\Support\Facades\Schema;
use LBHurtado\XChange\Contracts\CommercialComponentEconomicsResolverContract;
use LBHurtado\XChange\Models\CommercialComponentEconomics;
use LBHurtado\XChange\Models\CommercialComponentEconomicsActivation;
use LBHurtado\XChange\Models\CommercialComponentEconomicsHead;
use LBHurtado\XChange\Models\CommercialOfferingActivation;
use LBHurtado\XCommerce\Data\CommercialComponentEconomicsSetData;

final readonly class DatabaseCommercialComponentEconomicsResolver implements CommercialComponentEconomicsResolverContract
{
    public function __construct(private CommercialComponentEconomicsManifestCompiler $manifests) {}

    public function resolve(string $profile): CommercialComponentEconomicsSetData
    {
        if (! Schema::hasTable('x_change_commercial_component_economics_heads')) {
            throw new \DomainException('Commercial Component Economics storage is not ready.');
        }

        $head = CommercialComponentEconomicsHead::query()
            ->with('currentActivation.economics.offering')
            ->whereKey($profile)
            ->first();
        $activation = $head?->currentActivation;
        $economics = $activation?->economics;

        if (! $activation instanceof CommercialComponentEconomicsActivation
            || ! $economics instanceof CommercialComponentEconomics
            || $activation->profile !== $profile
            || $economics->profile !== $profile) {
            throw new \DomainException("Commercial Component Economics profile [{$profile}] has no active version.");
        }

        $offeringActivation = CommercialOfferingActivation::query()
            ->where('profile', $profile)
            ->whereNull('deactivated_at')
            ->first();
        if (! $offeringActivation instanceof CommercialOfferingActivation
            || $offeringActivation->commercial_offering_id !== $economics->commercial_offering_id
            || $offeringActivation->snapshot_hash !== $economics->offering_snapshot_hash
            || $economics->offering_snapshot_hash !== $economics->offering->snapshot_hash
            || $economics->snapshot_hash !== $economics->economics()->snapshotHash()) {
            throw new \DomainException('Active Commercial Component Economics evidence is inconsistent.');
        }

        $manifest = $this->manifests->parse(
            $economics->artifact_yaml,
            $economics->offering->offering(),
            $economics->offering_manifest_hash,
        );
        if ($manifest->profile !== $profile
            || $manifest->schema !== $economics->artifact_schema
            || $manifest->hash !== $economics->artifact_hash) {
            throw new \DomainException('Active Commercial Component Economics manifest evidence is inconsistent.');
        }

        return $manifest->componentEconomics;
    }
}
