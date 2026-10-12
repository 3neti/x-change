<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Configuration;

use Composer\InstalledVersions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LBHurtado\XChange\Models\XChangeInstallationManifest;
use RuntimeException;

final readonly class CommissioningReleaseRefresher
{
    public function __construct(
        private PreInstallReadinessInspector $readiness,
        private DeploymentConfigurationInspector $deployment,
        private CommissioningConfigurationFingerprint $fingerprint,
        private SystemPrincipalAccountReadinessInspector $systemPrincipalAccount,
        private CommercialPrincipalAccountReadinessInspector $commercialPrincipalAccount,
    ) {}

    public function refresh(): string
    {
        if (! Schema::hasTable('x_change_installation_manifests')
            || ! XChangeInstallationManifest::query()->whereKey(CommissioningStateResolver::ManifestKey)->exists()) {
            return 'not_commissioned';
        }

        if (! $this->readiness->inspect()['ready']
            || ! $this->systemPrincipalAccount->inspect()['passed']
            || ! $this->commercialPrincipalAccount->inspect()['passed']) {
            throw new RuntimeException('Commissioning release refresh requires passing readiness and principal checks.');
        }

        $deployment = $this->deployment->inspect();

        return DB::transaction(function () use ($deployment): string {
            $manifest = XChangeInstallationManifest::query()
                ->whereKey(CommissioningStateResolver::ManifestKey)
                ->lockForUpdate()
                ->first();

            if (! $manifest instanceof XChangeInstallationManifest) {
                throw new RuntimeException('The commissioning manifest disappeared during release refresh.');
            }

            $expected = match ($manifest->manifest_version) {
                2 => $this->fingerprint->legacy(),
                CommissioningStateResolver::ManifestVersion => $this->fingerprint->current(),
                default => throw new RuntimeException('The commissioning manifest version requires guarded adoption.'),
            };

            if ($manifest->profile !== $deployment['profile']
                || $manifest->active_connection_references !== $deployment['active_connections']
                || ! hash_equals($manifest->configuration_fingerprint, $expected)) {
                throw new RuntimeException('The commissioning identity changed; review it before guarded adoption.');
            }

            $version = InstalledVersions::getPrettyVersion('3neti/x-change') ?? 'dev-source';
            $upgraded = $manifest->manifest_version === 2;

            if ($upgraded) {
                $manifest->manifest_version = CommissioningStateResolver::ManifestVersion;
                $manifest->configuration_fingerprint = $this->fingerprint->current();
            }

            if ($manifest->package_version !== $version) {
                $manifest->package_version = $version;
            }

            if (! $manifest->isDirty()) {
                return 'unchanged';
            }

            $manifest->save();

            return $upgraded ? 'upgraded' : 'refreshed';
        });
    }
}
