<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Commercial;

use Composer\InstalledVersions;
use Illuminate\Support\Facades\DB;
use LBHurtado\XChange\Enums\CommercialActivationAuthority;
use LBHurtado\XChange\Enums\CommercialGovernanceMode;
use LBHurtado\XChange\Enums\CommercialOfferingOrigin;
use LBHurtado\XChange\Enums\CommercialOfferingStatus;
use LBHurtado\XChange\Models\CommercialOffering;
use LBHurtado\XChange\Models\CommercialOfferingActivation;

final readonly class ProvisionCommercialBaselines
{
    public function __construct(
        private BootstrapCommercialOfferingFactory $factory,
        private ActivateCommercialOffering $activate,
        private CommercialGovernanceJournal $journal,
        private CommercialOfferingManifestCompiler $manifests,
        private BackfillCommercialOfferingManifests $manifestBackfill,
        private ProvisionCommercialComponentEconomicsBaselines $componentEconomicsBaselines,
    ) {}

    /** @return list<CommercialOfferingActivation> */
    public function provision(string $commissioningManifestReference): array
    {
        $this->manifestBackfill->execute();

        $mode = CommercialGovernanceMode::from((string) config(
            'x-change.commercial.offerings.governance_mode',
            CommercialGovernanceMode::BootstrapImmutable->value,
        ));
        $activations = [];

        foreach ((array) config('x-change.commercial.offerings.profiles', ['pay_code', 'account_funding']) as $profile) {
            $offering = $this->baseline((string) $profile, $commissioningManifestReference);
            $this->journal->recordOffering(
                $offering,
                'commercial.offering.baseline_provisioned',
                'commissioning-manifest',
                (string) $offering->commissioning_manifest_reference,
            );

            if ($mode !== CommercialGovernanceMode::BootstrapImmutable) {
                continue;
            }

            $activations[] = $this->activate->execute(
                offering: $offering,
                authority: CommercialActivationAuthority::CommissioningManifest,
                activationReference: 'commercial-baseline:'.$profile.':'.$offering->snapshot_hash,
            );
        }

        if ($this->allConfiguredProfilesAreActive()) {
            $this->componentEconomicsBaselines->provision($commissioningManifestReference);
        }

        return $activations;
    }

    private function allConfiguredProfilesAreActive(): bool
    {
        $profiles = collect((array) config(
            'x-change.commercial.offerings.profiles',
            ['pay_code', 'account_funding'],
        ))
            ->filter(static fn (mixed $profile): bool => is_string($profile) && trim($profile) !== '')
            ->map(static fn (string $profile): string => trim($profile))
            ->unique()
            ->values();

        return $profiles->isNotEmpty()
            && CommercialOfferingActivation::query()
                ->whereIn('profile', $profiles->all())
                ->whereNull('deactivated_at')
                ->distinct()
                ->count('profile') === $profiles->count();
    }

    private function baseline(string $profile, string $manifestReference): CommercialOffering
    {
        $snapshot = $this->factory->make($profile);
        $commercialManifest = $this->manifests->compile($profile, $snapshot);
        $packageVersion = InstalledVersions::getPrettyVersion('3neti/x-change') ?? 'dev-source';

        return DB::transaction(function () use ($profile, $snapshot, $commercialManifest, $packageVersion, $manifestReference): CommercialOffering {
            $existing = CommercialOffering::query()
                ->where('reference', $snapshot->reference)
                ->where('origin', CommercialOfferingOrigin::InstallationBaseline->value)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof CommercialOffering) {
                if ($existing->snapshot_hash !== $snapshot->snapshotHash()) {
                    throw new \DomainException("Commercial baseline [{$profile}] conflicts with its persisted snapshot.");
                }

                if ($existing->manifest_hash === null) {
                    $existing->forceFill([
                        'manifest_schema' => $commercialManifest->schema,
                        'manifest_hash' => $commercialManifest->hash,
                        'manifest_yaml' => $commercialManifest->yaml,
                    ])->save();
                } elseif ($existing->manifest_hash !== $commercialManifest->hash) {
                    throw new \DomainException("Commercial baseline [{$profile}] conflicts with its persisted manifest.");
                }

                return $existing->refresh();
            }

            if (CommercialOffering::query()->where('reference', $snapshot->reference)->exists()) {
                throw new \DomainException(
                    "Commercial baseline [{$profile}] cannot be inserted after governed revisions already exist.",
                );
            }

            return CommercialOffering::query()->create([
                'reference' => $snapshot->reference,
                'version' => 1,
                'profile' => $profile,
                'status' => CommercialOfferingStatus::Published,
                'origin' => CommercialOfferingOrigin::InstallationBaseline,
                'currency' => $snapshot->catalog->currency,
                'snapshot_hash' => $snapshot->snapshotHash(),
                'snapshot' => $snapshot->toArray(),
                'manifest_schema' => $commercialManifest->schema,
                'manifest_hash' => $commercialManifest->hash,
                'manifest_yaml' => $commercialManifest->yaml,
                'source_package' => '3neti/x-change',
                'source_package_version' => $packageVersion,
                'commissioning_manifest_reference' => $manifestReference,
                'effective_at' => $snapshot->effectiveAt,
            ]);
        }, attempts: 5);
    }
}
