<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Commercial;

use Illuminate\Support\Facades\DB;
use LBHurtado\XChange\Enums\CommercialOfferingOrigin;
use LBHurtado\XChange\Models\CommercialOffering;

final readonly class RepairCommercialOfferingManifest
{
    public function __construct(
        private CommercialOfferingManifestCompiler $manifests,
        private CommercialGovernanceJournal $journal,
    ) {}

    public function execute(string $profile, string $authorityReference): CommercialOffering
    {
        return DB::transaction(function () use ($profile, $authorityReference): CommercialOffering {
            $offering = CommercialOffering::query()
                ->where('profile', $profile)
                ->where('origin', CommercialOfferingOrigin::InstallationBaseline->value)
                ->lockForUpdate()
                ->firstOrFail();

            $snapshot = $offering->offering();

            $manifest = $this->manifests->compile($profile, $snapshot);
            $previousSnapshotHash = $offering->snapshot_hash;
            $changed = $offering->manifest_schema !== $manifest->schema
                || $offering->manifest_hash !== $manifest->hash
                || $offering->manifest_yaml !== $manifest->yaml
                || $offering->snapshot_hash !== $snapshot->snapshotHash();

            if (! $changed) {
                return $offering;
            }

            $previousHash = $offering->manifest_hash;
            $offering->forceFill([
                'snapshot_hash' => $snapshot->snapshotHash(),
                'manifest_schema' => $manifest->schema,
                'manifest_hash' => $manifest->hash,
                'manifest_yaml' => $manifest->yaml,
            ])->save();

            $this->journal->recordOffering(
                $offering,
                'commercial.offering.baseline_manifest_repaired',
                'commissioning-manifest',
                $authorityReference,
                [
                    'previous_snapshot_hash' => $previousSnapshotHash,
                    'previous_manifest_hash' => $previousHash,
                    'manifest_hash' => $manifest->hash,
                ],
                $manifest->hash,
            );

            return $offering->refresh();
        }, attempts: 5);
    }
}
