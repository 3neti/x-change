<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Configuration;

use Composer\InstalledVersions;
use LBHurtado\XChange\Models\XChangeInstallationManifest;
use RuntimeException;

final readonly class CommissioningManifestRecorder
{
    public function __construct(
        private DeploymentConfigurationInspector $deployment,
        private CommissioningConfigurationFingerprint $fingerprint,
        private SystemPrincipalAccountReadinessInspector $systemPrincipalAccount,
    ) {}

    public function record(): XChangeInstallationManifest
    {
        if (! $this->systemPrincipalAccount->inspect()['passed']) {
            throw new RuntimeException(
                'Commissioning requires a persisted non-interactive system principal and Account.',
            );
        }

        $deployment = $this->deployment->inspect();

        return XChangeInstallationManifest::query()->updateOrCreate(
            ['key' => CommissioningStateResolver::ManifestKey],
            [
                'manifest_version' => CommissioningStateResolver::ManifestVersion,
                'package_version' => InstalledVersions::getPrettyVersion('3neti/x-change')
                    ?? 'dev-source',
                'profile' => $deployment['profile'],
                'active_connection_references' => $deployment['active_connections'],
                'configuration_fingerprint' => $this->fingerprint->current(),
                'completed_at' => now(),
            ],
        );
    }
}
