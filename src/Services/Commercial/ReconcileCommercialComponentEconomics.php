<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Commercial;

use LBHurtado\XChange\Enums\CommercialActivationAuthority;
use LBHurtado\XChange\Enums\CommercialOfferingOrigin;
use LBHurtado\XChange\Models\CommercialComponentEconomicsActivation;
use LBHurtado\XChange\Models\CommercialComponentEconomicsHead;
use LBHurtado\XChange\Models\CommercialOfferingActivation;

final readonly class ReconcileCommercialComponentEconomics
{
    public function __construct(
        private BootstrapCommercialComponentEconomicsFactory $factory,
        private CommercialComponentEconomicsManifestCompiler $manifests,
        private PersistCommercialComponentEconomicsManifest $persist,
        private ActivateCommercialComponentEconomics $activate,
    ) {}

    public function execute(string $profile, string $commissioningManifestReference): CommercialComponentEconomicsActivation
    {
        $head = CommercialComponentEconomicsHead::query()
            ->with('currentActivation.economics.offering')
            ->whereKey($profile)
            ->firstOrFail();
        $current = $head->currentActivation;
        $economics = $current?->economics;

        $offering = CommercialOfferingActivation::query()
            ->with('offering')
            ->where('profile', $profile)
            ->whereNull('deactivated_at')
            ->latest('activated_at')
            ->firstOrFail()
            ->offering;
        $manifest = $this->manifests->compile(
            $profile,
            $offering->offering(),
            (string) $offering->manifest_hash,
            $this->factory->make($profile, $offering->offering()),
        );

        if ($economics !== null
            && $economics->commercial_offering_id === $offering->getKey()
            && $manifest->hash === $economics->artifact_hash) {
            return $current;
        }

        $persisted = $this->persist->execute(
            offering: $offering,
            manifest: $manifest,
            reference: $economics?->reference ?? 'component-economics:'.$profile,
            version: ((int) $this->persistedVersion($profile)) + 1,
            origin: CommercialOfferingOrigin::InstallationBaseline,
            authority: CommercialActivationAuthority::CommissioningManifest,
            commissioningManifestReference: $commissioningManifestReference,
        );

        return $this->activate->execute(
            economics: $persisted,
            authority: CommercialActivationAuthority::CommissioningManifest,
            activationReference: 'component-economics-reconciliation:'.$profile.':'.$manifest->hash,
        );
    }

    private function persistedVersion(string $profile): int
    {
        return (int) \LBHurtado\XChange\Models\CommercialComponentEconomics::query()
            ->where('profile', $profile)
            ->max('version');
    }
}
