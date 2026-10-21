<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Commercial;

use Illuminate\Console\Command;
use LBHurtado\XChange\Models\XChangeInstallationManifest;
use LBHurtado\XChange\Services\Commercial\ReconcileCommercialComponentEconomics;
use LBHurtado\XChange\Services\Commercial\RepairCommercialOfferingManifest;
use LBHurtado\XChange\Services\Configuration\CommissioningStateResolver;
use Throwable;

final class RepairCommercialOfferingManifestCommand extends Command
{
    protected $signature = 'x-change:commercial:repair-baseline-manifest
        {--profile=account_funding : Commercial profile to repair}
        {--json : Output JSON}';

    protected $description = 'Repair installation baseline manifest evidence and reconcile its component economics.';

    public function handle(
        RepairCommercialOfferingManifest $repair,
        ReconcileCommercialComponentEconomics $reconcile,
    ): int {
        $manifest = XChangeInstallationManifest::query()
            ->whereKey(CommissioningStateResolver::ManifestKey)
            ->first();
        $profile = trim((string) $this->option('profile'));

        if (! $manifest instanceof XChangeInstallationManifest) {
            return $this->failure('A completed X-Change installation manifest is required.');
        }

        if ($profile === '') {
            return $this->failure('A commercial profile is required.');
        }

        try {
            $offering = $repair->execute(
                $profile,
                'installation-manifest:'.$manifest->configuration_fingerprint,
            );
            $activation = $reconcile->execute(
                $profile,
                'installation-manifest:'.$manifest->configuration_fingerprint,
            );
        } catch (Throwable $exception) {
            report($exception);

            return $this->failure($exception->getMessage());
        }

        $payload = [
            'schema' => 'x-change.commercial-baseline-manifest-repair.v1',
            'success' => true,
            'profile' => $profile,
            'offering_id' => $offering->getKey(),
            'manifest_hash' => $offering->manifest_hash,
            'activation_id' => $activation->getKey(),
            'activation_reference' => $activation->activation_reference,
        ];

        if ((bool) $this->option('json')) {
            $this->line(json_encode($payload, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->components->info("Commercial baseline manifest [{$profile}] repaired and reconciled.");
        }

        return self::SUCCESS;
    }

    private function failure(string $message): int
    {
        $payload = [
            'schema' => 'x-change.commercial-baseline-manifest-repair.v1',
            'success' => false,
            'message' => $message,
        ];

        if ((bool) $this->option('json')) {
            $this->line(json_encode($payload, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->components->error($message);
        }

        return self::FAILURE;
    }
}
