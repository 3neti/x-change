<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Configuration;

use Illuminate\Support\Facades\Schema;
use LBHurtado\XChange\Data\Configuration\CommissioningStateData;
use LBHurtado\XChange\Enums\CommissioningState;
use LBHurtado\XChange\Models\XChangeInstallationManifest;
use Throwable;

final readonly class CommissioningStateResolver
{
    public const ManifestKey = 'primary';

    public const ManifestVersion = 1;

    public function __construct(
        private PreInstallReadinessInspector $readiness,
        private CommissioningConfigurationFingerprint $fingerprint,
    ) {}

    public function resolve(): CommissioningStateData
    {
        try {
            $readiness = $this->readiness->inspect();

            if (! $readiness['ready']) {
                return new CommissioningStateData(
                    CommissioningState::ConfigurationRequired,
                    $readiness['profile'],
                    $readiness['missing_variables'],
                    'deployment_configuration_incomplete',
                );
            }

            if (! Schema::hasTable('x_change_installation_manifests')) {
                return new CommissioningStateData(
                    CommissioningState::ReadyToInstall,
                    $readiness['profile'],
                    reason: 'installation_manifest_table_missing',
                );
            }

            $manifest = XChangeInstallationManifest::query()->find(self::ManifestKey);

            if (! $manifest instanceof XChangeInstallationManifest) {
                return new CommissioningStateData(
                    CommissioningState::InstallationIncomplete,
                    $readiness['profile'],
                    reason: 'installation_manifest_missing',
                );
            }

            if (
                $manifest->manifest_version !== self::ManifestVersion
                || $manifest->profile !== $readiness['profile']
                || ! hash_equals(
                    $manifest->configuration_fingerprint,
                    $this->fingerprint->current(),
                )
            ) {
                return new CommissioningStateData(
                    CommissioningState::InstallationIncomplete,
                    $readiness['profile'],
                    reason: 'installation_manifest_stale',
                );
            }

            return new CommissioningStateData(
                CommissioningState::Operational,
                $readiness['profile'],
            );
        } catch (Throwable) {
            return new CommissioningStateData(
                CommissioningState::InstallationIncomplete,
                (string) config('x-change.deployment.profile', 'development'),
                reason: 'commissioning_state_unavailable',
            );
        }
    }
}
