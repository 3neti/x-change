<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Commercial;

use Illuminate\Console\Command;
use LBHurtado\XChange\Models\XChangeInstallationManifest;
use LBHurtado\XChange\Services\Commercial\CommercialGovernanceInspector;
use LBHurtado\XChange\Services\Commercial\ProvisionCommercialBaselines;
use LBHurtado\XChange\Services\Configuration\CommissioningStateResolver;
use Throwable;

final class ProvisionCommercialBaselinesCommand extends Command
{
    protected $signature = 'x-change:commercial:provision-baselines {--json : Output JSON}';

    protected $description = 'Idempotently provision governed baseline offerings for an existing commissioned installation.';

    public function handle(
        ProvisionCommercialBaselines $baselines,
        CommercialGovernanceInspector $governance,
    ): int {
        $manifest = XChangeInstallationManifest::query()
            ->whereKey(CommissioningStateResolver::ManifestKey)
            ->first();

        if (! $manifest instanceof XChangeInstallationManifest) {
            return $this->renderFailure('A completed X-Change installation manifest is required.');
        }

        try {
            $baselines->provision(
                'installation-manifest:'.$manifest->configuration_fingerprint,
            );
        } catch (Throwable $exception) {
            report($exception);

            return $this->renderFailure($exception->getMessage());
        }

        $status = $governance->inspect();
        $payload = [
            'schema' => 'x-change.commercial-baseline-provisioning.v1',
            'success' => $status['operational'] === true,
            'idempotent' => true,
            'governance' => $status,
        ];

        if ((bool) $this->option('json')) {
            $this->line(json_encode($payload, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->components->info('Commercial baseline governance is ready.');
            $this->line((string) $status['message']);
        }

        return $payload['success'] ? self::SUCCESS : self::FAILURE;
    }

    private function renderFailure(string $message): int
    {
        if ((bool) $this->option('json')) {
            $this->line(json_encode([
                'schema' => 'x-change.commercial-baseline-provisioning.v1',
                'success' => false,
                'message' => $message,
            ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->components->error($message);
        }

        return self::FAILURE;
    }
}
