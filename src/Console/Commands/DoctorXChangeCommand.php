<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use LBHurtado\XChange\Contracts\ProviderRuntimeSettingsResolverContract;
use LBHurtado\XChange\Contracts\XChangeProviderTopologyResolverContract;
use LBHurtado\XChange\Services\Cockpit\CockpitOperatorIssuanceActivityRuntimeProfileInspector;
use LBHurtado\XChange\Services\Configuration\CommissioningStateResolver;
use LBHurtado\XChange\Services\Configuration\PreInstallReadinessInspector;
use LBHurtado\XChange\Services\PublishedAssetDriftDetector;
use Throwable;

class DoctorXChangeCommand extends Command
{
    protected $signature = 'x-change:doctor
        {--json : Output JSON}
        {--strict : Return a non-zero exit status when any check fails}
        {--assets : Inspect published x-change frontend asset drift only}
        {--pre-install : Inspect only checks that are safe before migrations and publishing}
        {--operator-activity-runtime : Inspect Cockpit operator activity runtime configuration only}';

    protected $description = 'Inspect X-Change turnkey installation readiness.';

    public function handle(
        XChangeProviderTopologyResolverContract $topologies,
        ProviderRuntimeSettingsResolverContract $settings,
        PublishedAssetDriftDetector $publishedAssets,
        CockpitOperatorIssuanceActivityRuntimeProfileInspector $operatorActivityRuntimeProfile,
        PreInstallReadinessInspector $preInstallReadiness,
        CommissioningStateResolver $commissioning,
    ): int {
        $checks = $this->option('pre-install')
            ? $preInstallReadiness->inspect()['checks']
            : ($this->option('operator-activity-runtime')
            ? [$this->operatorActivityRuntimeProfileCheck($operatorActivityRuntimeProfile)]
            : ($this->option('assets')
            ? [$this->publishedAssetCheck($publishedAssets)]
            : [
                $this->check('x-change config', config('x-change') !== [], 'config(x-change) is loaded'),
                ...$preInstallReadiness->inspect()['checks'],
                $this->commissioningCheck($commissioning),
                $this->check('onboarding package', class_exists('LBHurtado\\Onboarding\\OnboardingServiceProvider'), '3neti/onboarding is installed'),
                $this->check('onboarding config', config('onboarding') !== [], 'config(onboarding) is loaded'),
                $this->check('onboarding sessions table', $this->hasTable('onboarding_sessions'), 'onboarding_sessions table exists'),
                $this->check('users.mobile column', $this->hasColumn('users', 'mobile'), 'users.mobile exists'),
                $this->check('users.mobile_verified_at column', $this->hasColumn('users', 'mobile_verified_at'), 'users.mobile_verified_at exists'),
                $this->check('users.identity_level column', $this->hasColumn('users', 'identity_level'), 'users.identity_level exists'),
                $this->check('Fortify mobile username', config('fortify.username') === 'mobile', 'fortify.username is mobile'),
                $this->providerTopologyCheck($topologies),
                $this->providerRuntimeSettingsCheck($settings),
            ]));

        $passed = collect($checks)->every(
            static fn (array $check): bool => $check['passed'] === true,
        );
        $strict = (bool) $this->option('strict');
        $exitCode = $strict && ! $passed
            ? self::FAILURE
            : self::SUCCESS;

        if ($this->option('json')) {
            $this->line(json_encode([
                'schema' => 'x-change.readiness-report.v1',
                'success' => $passed,
                'strict' => $strict,
                'summary' => [
                    'passed' => collect($checks)->where('passed', true)->count(),
                    'failed' => collect($checks)->where('passed', false)->count(),
                ],
                'checks' => $checks,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $exitCode;
        }

        $this->info('X-Change doctor');

        foreach ($checks as $check) {
            $message = sprintf('%s: %s', $check['name'], $check['message']);

            if ($check['passed']) {
                $this->components->info($message);

                continue;
            }

            $this->components->warn($message);
        }

        if ($strict && ! $passed) {
            $this->components->error(
                'Strict readiness failed. Deployment must not continue.',
            );
        }

        return $exitCode;
    }

    /**
     * @return array{name: string, passed: bool, message: string, meta: array<string, mixed>}
     */
    protected function publishedAssetCheck(PublishedAssetDriftDetector $publishedAssets): array
    {
        $result = $publishedAssets->inspect();

        return $this->check($result['name'], $result['passed'], $result['message'], [
            'summary' => $result['summary'],
            'files' => $result['files'],
        ]);
    }

    /**
     * @return array{name: string, passed: bool, message: string, meta: array<string, mixed>}
     */
    protected function operatorActivityRuntimeProfileCheck(CockpitOperatorIssuanceActivityRuntimeProfileInspector $inspector): array
    {
        return $this->check(
            'cockpit operator activity runtime profile',
            true,
            'operator activity runtime profile inspected',
            $inspector->inspect()->toArray(),
        );
    }

    /**
     * @return array{name: string, passed: bool, message: string, meta: array<string, mixed>}
     */
    protected function providerTopologyCheck(XChangeProviderTopologyResolverContract $topologies): array
    {
        try {
            $topology = $topologies->resolve();

            return $this->check('provider topology', true, 'provider topology resolves', [
                'key' => $topology->key(),
                'requires_provider_credentials_per_user' => $topology->requiresProviderCredentialsPerUser(),
                'uses_local_ledger_as_source_of_truth' => $topology->usesLocalLedgerAsSourceOfTruth(),
            ]);
        } catch (Throwable $e) {
            return $this->check('provider topology', false, $e->getMessage());
        }
    }

    /**
     * @return array{name: string, passed: bool, message: string, meta: array<string, mixed>}
     */
    protected function commissioningCheck(CommissioningStateResolver $commissioning): array
    {
        if (! (bool) config('x-change.commissioning.enabled', true)) {
            return $this->check(
                'commissioning manifest',
                true,
                'commissioning gate is explicitly disabled',
            );
        }

        $state = $commissioning->resolve();

        return $this->check(
            'commissioning manifest',
            $state->isOperational(),
            $state->isOperational()
                ? 'installation manifest matches the active deployment configuration'
                : 'installation is not commissioned ['.$state->state->value.']',
            ['state' => $state->state->value, 'reason' => $state->reason],
        );
    }

    /**
     * @return array{name: string, passed: bool, message: string, meta: array<string, mixed>}
     */
    protected function providerRuntimeSettingsCheck(ProviderRuntimeSettingsResolverContract $settings): array
    {
        try {
            $provider = $settings->provider();

            return $this->check('provider runtime settings', true, 'provider runtime settings resolve', [
                'provider' => $provider,
                'topology' => $settings->topology($provider),
                'enabled' => $settings->isEnabled($provider),
                'allows_live_provider_scenarios' => $settings->allowsLiveProviderScenarios(),
            ]);
        } catch (Throwable $e) {
            return $this->check('provider runtime settings', false, $e->getMessage());
        }
    }

    /**
     * @return array{name: string, passed: bool, message: string, meta: array<string, mixed>}
     */
    protected function check(string $name, bool $passed, string $message, array $meta = []): array
    {
        return [
            'name' => $name,
            'passed' => $passed,
            'message' => $message,
            'meta' => $meta,
        ];
    }

    protected function hasTable(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (Throwable) {
            return false;
        }
    }

    protected function hasColumn(string $table, string $column): bool
    {
        try {
            return Schema::hasTable($table) && Schema::hasColumn($table, $column);
        } catch (Throwable) {
            return false;
        }
    }
}
