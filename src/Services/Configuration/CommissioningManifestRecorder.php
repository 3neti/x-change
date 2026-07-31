<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Configuration;

use Composer\InstalledVersions;
use LBHurtado\XChange\Models\XChangeInstallationManifest;

final readonly class CommissioningManifestRecorder
{
    public function __construct(
        private DeploymentConfigurationInspector $deployment,
        private CommissioningConfigurationFingerprint $fingerprint,
    ) {}

    public function record(): XChangeInstallationManifest
    {
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
