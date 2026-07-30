<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use LBHurtado\XChange\Contracts\ProviderRuntimeSettingsResolverContract;
use LBHurtado\XChange\Contracts\XChangeProviderTopologyResolverContract;
use LBHurtado\XChange\Services\Cockpit\CockpitOperatorIssuanceActivityRuntimeProfileInspector;
use LBHurtado\XChange\Services\PublishedAssetDriftDetector;
use Throwable;

class DoctorXChangeCommand extends Command
{
    protected $signature = 'x-change:doctor
        {--json : Output JSON}
        {--strict : Return a non-zero exit status when any check fails}
        {--assets : Inspect published x-change frontend asset drift only}
        {--operator-activity-runtime : Inspect Cockpit operator activity runtime configuration only}';

    protected $description = 'Inspect X-Change turnkey installation readiness.';

    public function handle(
        XChangeProviderTopologyResolverContract $topologies,
        ProviderRuntimeSettingsResolverContract $settings,
        PublishedAssetDriftDetector $publishedAssets,
        CockpitOperatorIssuanceActivityRuntimeProfileInspector $operatorActivityRuntimeProfile,
    ): int {
        $checks = $this->option('operator-activity-runtime')
            ? [$this->operatorActivityRuntimeProfileCheck($operatorActivityRuntimeProfile)]
            : ($this->option('assets')
            ? [$this->publishedAssetCheck($publishedAssets)]
            : [
                $this->check('x-change config', config('x-change') !== [], 'config(x-change) is loaded'),
                $this->check('onboarding package', class_exists('LBHurtado\\Onboarding\\OnboardingServiceProvider'), '3neti/onboarding is installed'),
                $this->check('onboarding config', config('onboarding') !== [], 'config(onboarding) is loaded'),
                $this->check('onboarding sessions table', $this->hasTable('onboarding_sessions'), 'onboarding_sessions table exists'),
                $this->check('users.mobile column', $this->hasColumn('users', 'mobile'), 'users.mobile exists'),
                $this->check('users.mobile_verified_at column', $this->hasColumn('users', 'mobile_verified_at'), 'users.mobile_verified_at exists'),
                $this->check('users.identity_level column', $this->hasColumn('users', 'identity_level'), 'users.identity_level exists'),
                $this->check('Fortify mobile username', config('fortify.username') === 'mobile', 'fortify.username is mobile'),
                $this->productionApplicationSecurityCheck(),
                $this->productionOnboardingOtpCheck(),
                $this->queueRuntimeCheck(),
                $this->schedulerLockCacheCheck(),
                $this->providerTopologyCheck($topologies),
                $this->providerRuntimeSettingsCheck($settings),
            ]);

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
    protected function queueRuntimeCheck(): array
    {
        $connection = (string) config('queue.default');
        $durable = ! in_array($connection, ['', 'sync', 'null'], true);

        return $this->check(
            'durable queue runtime',
            $durable,
            $durable
                ? "queue connection [{$connection}] can run asynchronously"
                : "queue connection [{$connection}] cannot provide durable asynchronous processing",
            [
                'connection' => $connection,
                'required_queues' => [
                    'default',
                    'x-change-feedback',
                    'x-change-funding',
                ],
            ],
        );
    }

    /**
     * @return array{name: string, passed: bool, message: string, meta: array<string, mixed>}
     */
    protected function productionOnboardingOtpCheck(): array
    {
        $environment = (string) config('app.env');
        $production = $environment === 'production';
        $enabled = (bool) config('x-change.onboarding.mobile_verification.enabled', true);
        $required = (bool) config('x-change.onboarding.voucher.require_otp', true);
        $driver = (string) config('x-change.withdrawal.otp.driver', 'null');
        $showsLocalCode = (bool) config(
            'x-change.onboarding.mobile_verification.show_local_code',
            false,
        );
        $ready = ! $production || (
            $enabled
            && $required
            && $driver !== ''
            && $driver !== 'null'
            && ! $showsLocalCode
        );

        return $this->check(
            'production onboarding OTP',
            $ready,
            $ready
                ? ($production
                    ? "onboarding OTP uses the configured [{$driver}] delivery driver"
                    : "production-only OTP gate is not required in [{$environment}]")
                : 'production onboarding requires a non-null OTP driver with local code display disabled',
            [
                'environment' => $environment,
                'mobile_verification_enabled' => $enabled,
                'otp_required' => $required,
                'driver' => $driver,
                'local_code_visible' => $showsLocalCode,
            ],
        );
    }

    /**
     * @return array{name: string, passed: bool, message: string, meta: array<string, mixed>}
     */
    protected function productionApplicationSecurityCheck(): array
    {
        $environment = (string) config('app.env');
        $production = $environment === 'production';
        $debug = (bool) config('app.debug');
        $hasStableKey = is_string(config('app.key'))
            && trim((string) config('app.key')) !== '';
        $url = (string) config('app.url');
        $usesHttps = str_starts_with($url, 'https://');
        $secureCookies = (bool) config('session.secure');
        $ready = ! $production || (
            ! $debug
            && $hasStableKey
            && $usesHttps
            && $secureCookies
        );

        return $this->check(
            'production application security',
            $ready,
            $ready
                ? ($production
                    ? 'production debug, key, HTTPS, and cookie controls are ready'
                    : "production-only application security gate is not required in [{$environment}]")
                : 'production requires debug off, a stable key, HTTPS, and secure cookies',
            [
                'environment' => $environment,
                'debug' => $debug,
                'app_key_configured' => $hasStableKey,
                'https' => $usesHttps,
                'secure_cookies' => $secureCookies,
            ],
        );
    }

    /**
     * @return array{name: string, passed: bool, message: string, meta: array<string, mixed>}
     */
    protected function schedulerLockCacheCheck(): array
    {
        $store = (string) config('cache.default');
        $shared = in_array($store, ['database', 'dynamodb', 'memcached', 'redis'], true);

        return $this->check(
            'shared scheduler lock cache',
            $shared,
            $shared
                ? "cache store [{$store}] supports shared scheduler locks"
                : "cache store [{$store}] is not approved for multi-node scheduler locks",
            ['store' => $store],
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
