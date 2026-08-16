<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Commercial;

use Composer\InstalledVersions;
use Illuminate\Support\Facades\DB;
use LBHurtado\XChange\Data\Commercial\CommercialComponentEconomicsManifestData;
use LBHurtado\XChange\Enums\CommercialActivationAuthority;
use LBHurtado\XChange\Enums\CommercialOfferingOrigin;
use LBHurtado\XChange\Models\CommercialComponentEconomics;
use LBHurtado\XChange\Models\CommercialOffering;

final readonly class PersistCommercialComponentEconomicsManifest
{
    public function __construct(
        private CommercialComponentEconomicsManifestCompiler $manifests,
        private CommercialGovernanceJournal $journal,
    ) {}

    public function execute(
        CommercialOffering $offering,
        CommercialComponentEconomicsManifestData $manifest,
        string $reference,
        int $version,
        CommercialOfferingOrigin $origin,
        CommercialActivationAuthority $authority,
        ?string $commissioningManifestReference = null,
    ): CommercialComponentEconomics {
        $reference = trim($reference);
        if ($reference === '' || $version < 1) {
            throw new \DomainException('Commercial Component Economics requires a stable reference and positive version.');
        }

        $persisted = DB::transaction(function () use (
            $offering,
            $manifest,
            $reference,
            $version,
            $origin,
            $authority,
            $commissioningManifestReference,
        ): CommercialComponentEconomics {
            $lockedOffering = CommercialOffering::query()->lockForUpdate()->findOrFail($offering->getKey());
            $parsed = $this->manifests->parse(
                $manifest->yaml,
                $lockedOffering->offering(),
                (string) $lockedOffering->manifest_hash,
            );

            if ($manifest->profile !== $lockedOffering->profile
                || $manifest->offeringReference !== $lockedOffering->reference
                || $manifest->offeringVersion !== $lockedOffering->version
                || $manifest->offeringSnapshotHash !== $lockedOffering->snapshot_hash
                || $manifest->offeringManifestHash !== $lockedOffering->manifest_hash
                || $parsed->hash !== $manifest->hash
                || $parsed->componentEconomics->snapshotHash() !== $manifest->componentEconomics->snapshotHash()) {
                throw new \DomainException('Commercial Component Economics manifest does not match its Commercial Offering evidence.');
            }

            $existingArtifact = CommercialComponentEconomics::query()
                ->where('artifact_hash', $manifest->hash)
                ->lockForUpdate()
                ->first();
            if ($existingArtifact instanceof CommercialComponentEconomics) {
                if ($existingArtifact->reference !== $reference
                    || $existingArtifact->version !== $version
                    || $existingArtifact->commercial_offering_id !== $lockedOffering->getKey()
                    || $existingArtifact->offering_snapshot_hash !== $manifest->offeringSnapshotHash
                    || $existingArtifact->offering_manifest_hash !== $manifest->offeringManifestHash
                    || $existingArtifact->snapshot_hash !== $manifest->componentEconomics->snapshotHash()
                    || $existingArtifact->artifact_schema !== $manifest->schema
                    || $existingArtifact->artifact_yaml !== $manifest->yaml) {
                    throw new \DomainException('Commercial Component Economics manifest hash conflicts with prior evidence.');
                }

                return $existingArtifact;
            }

            $existingVersion = CommercialComponentEconomics::query()
                ->where('reference', $reference)
                ->where('version', $version)
                ->lockForUpdate()
                ->first();
            if ($existingVersion instanceof CommercialComponentEconomics) {
                throw new \DomainException('Commercial Component Economics reference and version conflict with prior evidence.');
            }

            return CommercialComponentEconomics::query()->create([
                'reference' => $reference,
                'version' => $version,
                'profile' => $manifest->profile,
                'origin' => $origin,
                'authority' => $authority,
                'commercial_offering_id' => $lockedOffering->getKey(),
                'offering_reference' => $manifest->offeringReference,
                'offering_version' => $manifest->offeringVersion,
                'offering_snapshot_hash' => $manifest->offeringSnapshotHash,
                'offering_manifest_hash' => $manifest->offeringManifestHash,
                'currency' => $manifest->componentEconomics->currency,
                'snapshot_hash' => $manifest->componentEconomics->snapshotHash(),
                'snapshot' => $manifest->componentEconomics->toArray(),
                'artifact_schema' => $manifest->schema,
                'artifact_hash' => $manifest->hash,
                'artifact_yaml' => $manifest->yaml,
                'source_package' => '3neti/x-change',
                'source_package_version' => InstalledVersions::getPrettyVersion('3neti/x-change') ?? 'dev-source',
                'commissioning_manifest_reference' => $commissioningManifestReference,
                'effective_at' => $lockedOffering->effective_at,
            ]);
        }, attempts: 5);

        $this->journal->recordComponentEconomics(
            $persisted,
            $origin === CommercialOfferingOrigin::InstallationBaseline
                ? 'commercial.component_economics.baseline_provisioned'
                : 'commercial.component_economics.persisted',
        );

        return $persisted;
    }
}
