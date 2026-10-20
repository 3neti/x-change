<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Commercial;

use Illuminate\Console\Command;
use LBHurtado\XChange\Models\XChangeInstallationManifest;
use LBHurtado\XChange\Services\Commercial\ReconcileCommercialComponentEconomics;
use LBHurtado\XChange\Services\Configuration\CommissioningStateResolver;
use Throwable;

final class ReconcileCommercialComponentEconomicsCommand extends Command
{
    protected $signature = 'x-change:commercial:reconcile-component-economics
        {--profile=pay_code : Commercial profile to reconcile}
        {--json : Output JSON}';

    protected $description = 'Reconcile commissioned component economics without maker-checker approval.';

    public function handle(ReconcileCommercialComponentEconomics $reconcile): int
    {
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
            $activation = $reconcile->execute(
                $profile,
                'installation-manifest:'.$manifest->configuration_fingerprint,
            );
        } catch (Throwable $exception) {
            report($exception);

            return $this->failure($exception->getMessage());
        }

        $payload = [
            'schema' => 'x-change.commercial-component-economics-reconciliation.v1',
            'success' => true,
            'profile' => $profile,
            'activation_id' => $activation->getKey(),
            'authority' => $activation->authority->value,
            'activation_reference' => $activation->activation_reference,
        ];

        if ((bool) $this->option('json')) {
            $this->line(json_encode($payload, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->components->info("Commercial Component Economics [{$profile}] reconciled without maker-checker approval.");
        }

        return self::SUCCESS;
    }

    private function failure(string $message): int
    {
        if ((bool) $this->option('json')) {
            $this->line(json_encode([
                'schema' => 'x-change.commercial-component-economics-reconciliation.v1',
                'success' => false,
                'message' => $message,
            ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->components->error($message);
        }

        return self::FAILURE;
    }
}
