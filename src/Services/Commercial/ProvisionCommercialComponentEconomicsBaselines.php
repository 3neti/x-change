<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Commercial;

use LBHurtado\XChange\Enums\CommercialActivationAuthority;
use LBHurtado\XChange\Enums\CommercialOfferingOrigin;
use LBHurtado\XChange\Models\CommercialComponentEconomicsActivation;
use LBHurtado\XChange\Models\CommercialOfferingActivation;

final readonly class ProvisionCommercialComponentEconomicsBaselines
{
    public function __construct(
        private BootstrapCommercialComponentEconomicsFactory $factory,
        private CommercialComponentEconomicsManifestCompiler $manifests,
        private PersistCommercialComponentEconomicsManifest $persist,
        private ActivateCommercialComponentEconomics $activate,
    ) {}

    /** @return list<CommercialComponentEconomicsActivation> */
    public function provision(string $commissioningManifestReference): array
    {
        $activations = [];

        foreach ((array) config('x-change.commercial.offerings.profiles', ['pay_code', 'account_funding']) as $configuredProfile) {
            $profile = (string) $configuredProfile;
            $offeringActivation = CommercialOfferingActivation::query()
                ->with('offering')
                ->where('profile', $profile)
                ->whereNull('deactivated_at')
                ->latest('activated_at')
                ->firstOrFail();
            $offering = $offeringActivation->offering;
            $economics = $this->factory->make($profile, $offering->offering());
            $manifest = $this->manifests->compile($profile, $offering->offering(), (string) $offering->manifest_hash, $economics);
            $persisted = $this->persist->execute(
                offering: $offering,
                manifest: $manifest,
                reference: 'component-economics:'.$profile,
                version: 1,
                origin: CommercialOfferingOrigin::InstallationBaseline,
                authority: CommercialActivationAuthority::CommissioningManifest,
                commissioningManifestReference: $commissioningManifestReference,
            );
            $activations[] = $this->activate->execute(
                $persisted,
                CommercialActivationAuthority::CommissioningManifest,
                'component-economics-baseline:'.$profile.':'.$manifest->hash,
            );
        }

        return $activations;
    }
}
