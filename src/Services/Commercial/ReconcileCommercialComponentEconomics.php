<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Commercial;

use LBHurtado\XChange\Enums\CommercialActivationAuthority;
use LBHurtado\XChange\Enums\CommercialOfferingOrigin;
use LBHurtado\XChange\Models\CommercialComponentEconomicsActivation;
use LBHurtado\XChange\Models\CommercialComponentEconomicsHead;

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

        if (! $current instanceof CommercialComponentEconomicsActivation || $economics === null) {
            throw new \DomainException("Commercial Component Economics profile [{$profile}] is not active.");
        }

        $offering = $economics->offering;
        $manifest = $this->manifests->compile(
            $profile,
            $offering->offering(),
            (string) $offering->manifest_hash,
            $this->factory->make($profile, $offering->offering()),
        );

        if ($manifest->hash === $economics->artifact_hash) {
            return $current;
        }

        $persisted = $this->persist->execute(
            offering: $offering,
            manifest: $manifest,
            reference: $economics->reference,
            version: $economics->version + 1,
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
}
