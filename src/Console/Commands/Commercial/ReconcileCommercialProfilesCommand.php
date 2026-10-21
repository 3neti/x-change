<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands\Commercial;

use Illuminate\Console\Command;
use LBHurtado\XChange\Models\XChangeInstallationManifest;
use LBHurtado\XChange\Services\Commercial\CommercialEconomicsReadiness;
use LBHurtado\XChange\Services\Commercial\ReconcileCommercialComponentEconomics;
use LBHurtado\XChange\Services\Configuration\CommissioningStateResolver;
use Throwable;

final class ReconcileCommercialProfilesCommand extends Command
{
    protected $signature = 'x-change:commercial:reconcile-profiles
        {--strict : Fail when any configured profile cannot be reconciled}
        {--json : Output JSON}';

    protected $description = 'Reconcile and inspect all configured commercial economics profiles.';

    public function handle(
        ReconcileCommercialComponentEconomics $reconcile,
        CommercialEconomicsReadiness $readiness,
    ): int {
        $manifest = XChangeInstallationManifest::query()
            ->whereKey(CommissioningStateResolver::ManifestKey)
            ->first();
        $profiles = collect((array) config('x-change.commercial.offerings.profiles', ['pay_code', 'account_funding']))
            ->filter(static fn (mixed $profile): bool => is_string($profile) && trim($profile) !== '')
            ->map(static fn (string $profile): string => trim($profile))
            ->unique()
            ->values()
            ->all();

        if (! $manifest instanceof XChangeInstallationManifest) {
            return $this->renderResult(false, $profiles, [], 'A completed X-Change installation manifest is required.');
        }

        $reconciled = [];
        $failures = [];
        foreach ($profiles as $profile) {
            try {
                $activation = $reconcile->execute(
                    $profile,
                    'installation-manifest:'.$manifest->configuration_fingerprint,
                );
                $reconciled[$profile] = [
                    'success' => true,
                    'activation_id' => $activation->getKey(),
                    'activation_reference' => $activation->activation_reference,
                ];
            } catch (Throwable $exception) {
                report($exception);
                $failures[$profile] = $exception->getMessage();
            }
        }

        $status = $readiness->inspect($profiles);
        $success = $status['ready'] === true;
        $strict = (bool) $this->option('strict');

        return $this->renderResult(
            $success || ! $strict,
            $profiles,
            [
                'reconciled' => $reconciled,
                'failures' => $failures,
                'readiness' => $status,
            ],
            $success ? null : 'One or more commercial economics profiles are not ready.',
        );
    }

    /** @param list<string> $profiles */
    private function renderResult(bool $success, array $profiles, array $payload, ?string $message = null): int
    {
        $result = [
            'schema' => 'x-change.commercial-component-economics-profiles.v1',
            'success' => $success,
            'strict' => (bool) $this->option('strict'),
            'profiles' => $profiles,
            ...$payload,
        ];
        if ($message !== null) {
            $result['message'] = $message;
        }

        if ((bool) $this->option('json')) {
            $this->line(json_encode($result, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } elseif ($success) {
            $this->components->info('Commercial economics profiles reconciled.');
        } else {
            $this->components->warn($message ?? 'Commercial economics profiles are not ready.');
        }

        return $success ? self::SUCCESS : self::FAILURE;
    }
}
