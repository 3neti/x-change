<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use LBHurtado\XChange\Contracts\XChangeProviderTopologyResolverContract;
use Throwable;

class DoctorXChangeCommand extends Command
{
    protected $signature = 'x-change:doctor
        {--json : Output JSON}';

    protected $description = 'Inspect X-Change turnkey installation readiness.';

    public function handle(XChangeProviderTopologyResolverContract $topologies): int
    {
        $checks = [
            $this->check('x-change config', config('x-change') !== [], 'config(x-change) is loaded'),
            $this->check('onboarding package', class_exists('LBHurtado\\Onboarding\\OnboardingServiceProvider'), '3neti/onboarding is installed'),
            $this->check('onboarding config', config('onboarding') !== [], 'config(onboarding) is loaded'),
            $this->check('onboarding sessions table', $this->hasTable('onboarding_sessions'), 'onboarding_sessions table exists'),
            $this->check('users.mobile column', $this->hasColumn('users', 'mobile'), 'users.mobile exists'),
            $this->check('users.mobile_verified_at column', $this->hasColumn('users', 'mobile_verified_at'), 'users.mobile_verified_at exists'),
            $this->check('users.identity_level column', $this->hasColumn('users', 'identity_level'), 'users.identity_level exists'),
            $this->check('Fortify mobile username', config('fortify.username') === 'mobile', 'fortify.username is mobile'),
            $this->providerTopologyCheck($topologies),
        ];

        if ($this->option('json')) {
            $this->line(json_encode([
                'success' => true,
                'checks' => $checks,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
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

        return self::SUCCESS;
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
