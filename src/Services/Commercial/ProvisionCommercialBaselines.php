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
    ) {}

    /** @return list<CommercialOfferingActivation> */
    public function provision(string $commissioningManifestReference): array
    {
        $mode = CommercialGovernanceMode::from((string) config(
            'x-change.commercial.offerings.governance_mode',
            CommercialGovernanceMode::BootstrapImmutable->value,
        ));
        $activations = [];

        foreach ((array) config('x-change.commercial.offerings.profiles', ['pay_code', 'account_funding']) as $profile) {
            $offering = $this->baseline((string) $profile, $commissioningManifestReference);

            if ($mode !== CommercialGovernanceMode::BootstrapImmutable) {
                continue;
            }

            $activations[] = $this->activate->execute(
                offering: $offering,
                authority: CommercialActivationAuthority::CommissioningManifest,
                activationReference: 'commercial-baseline:'.$profile.':'.$offering->snapshot_hash,
            );
        }

        return $activations;
    }

    private function baseline(string $profile, string $manifestReference): CommercialOffering
    {
        $snapshot = $this->factory->make($profile);
        $packageVersion = InstalledVersions::getPrettyVersion('3neti/x-change') ?? 'dev-source';

        return DB::transaction(function () use ($profile, $snapshot, $packageVersion, $manifestReference): CommercialOffering {
            $existing = CommercialOffering::query()
                ->where('reference', $snapshot->reference)
                ->where('origin', CommercialOfferingOrigin::InstallationBaseline->value)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof CommercialOffering) {
                if ($existing->snapshot_hash !== $snapshot->snapshotHash()) {
                    throw new \DomainException("Commercial baseline [{$profile}] conflicts with its persisted snapshot.");
                }

                return $existing;
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
                'source_package' => '3neti/x-change',
                'source_package_version' => $packageVersion,
                'commissioning_manifest_reference' => $manifestReference,
                'effective_at' => $snapshot->effectiveAt,
            ]);
        }, attempts: 5);
    }
}
