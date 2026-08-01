<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use LBHurtado\XChange\Services\Configuration\FrontendRuntimeDependencies;
use LBHurtado\XChange\Services\Configuration\HostApplicationIdentity;
use LBHurtado\XChange\Services\Configuration\LocalEnvironmentFileWriter;
use Throwable;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

final class SetupXChangeCommand extends Command
{
    protected $signature = 'x-change:setup
        {--profile= : development, netbank, paynamics, hybrid, or custom}
        {--target=local : Local setup target; use x-change:deploy for remote environments}
        {--name= : Compliant host display name such as x-PayOut}
        {--url= : Local application URL}
        {--write-env : Permit local .env preparation in non-interactive mode}
        {--env-path= : Defaults to the host .env}
        {--env-example-path= : Defaults to the host .env.example}
        {--manifest-path= : Defaults to x-change.deployment.yaml}
        {--system-principal-name= : Display name for the System Account}
        {--system-principal-email= : Stable System Account email}
        {--no-treasury : Explicitly leave Treasury commissioning incomplete}
        {--no-frontend : Skip npm dependency installation and production build}
        {--force : Refresh published package resources}
        {--dry-run : Show the setup plan without changing files or state}
        {--json : Output JSON}';

    protected $description = 'Configure, install, and verify x-change through one guided command.';

    public function handle(
        HostApplicationIdentity $identity,
        FrontendRuntimeDependencies $frontendDependencies,
        LocalEnvironmentFileWriter $environment,
    ): int {
        $interactive = $this->input->isInteractive() && ! $this->option('json');
        $target = trim((string) $this->option('target'));
        $profile = trim((string) ($this->option('profile') ?: ''));

        if ($interactive) {
            intro('X-CHANGE · Settlement Operating System');
            $profile = select(
                label: 'Provider profile',
                options: [
                    'development' => 'Development',
                    'netbank' => 'NetBank',
                    'paynamics' => 'Paynamics',
                    'hybrid' => 'NetBank and Paynamics',
                    'custom' => 'Custom connections',
                ],
                default: $profile !== '' ? $profile : 'development',
            );
        }

        if ($target !== 'local') {
            return $this->renderResult(false, 'x-change:setup is local-only; use x-change:deploy for remote environments.', [
                'target' => $target,
            ]);
        }

        $profile = $profile !== '' ? $profile : 'development';
        $resolvedIdentity = $identity->resolve($this->option('name'));
        $name = $resolvedIdentity['display_name'];
        $url = trim((string) ($this->option('url') ?: config('app.url')));

        if ($url === '' || str_contains($url, 'localhost')) {
            $url = 'http://'.$resolvedIdentity['slug'].'.test';
        }

        if ($interactive) {
            $name = text('Application name', default: $name, required: true);
            $resolvedIdentity = $identity->resolve($name);
            $name = $resolvedIdentity['display_name'];
            $url = text('Application URL', default: $url, required: true);
        }

        $systemEmail = trim((string) ($this->option('system-principal-email')
            ?: config('x-change.payout.system_user_id')));
        $systemEmail = $systemEmail !== ''
            ? $systemEmail
            : 'system@'.$resolvedIdentity['slug'].'.test';
        $systemName = trim((string) ($this->option('system-principal-name')
            ?: $name.' System'));
        $writeEnvironment = (
            (bool) $this->option('write-env')
            || ($interactive && confirm('Prepare the local .env file?', true))
        );
        $plan = [
            'target' => $target,
            'profile' => $profile,
            'application' => ['name' => $name, 'url' => $url],
            'write_local_environment' => $writeEnvironment,
            'steps' => [
                'configure',
                'adopt_host',
                'preflight',
                'install',
                ...((bool) $this->option('no-frontend') ? [] : ['frontend']),
                'manifest',
                'verify',
            ],
        ];

        if ($this->option('dry-run')) {
            return $this->renderResult(true, 'planned', $plan);
        }

        try {
            $configureExitCode = $this->call('x-change:configure', [
                '--profile' => $profile,
                '--path' => $this->option('env-example-path') ?: base_path('.env.example'),
                '--json' => (bool) $this->option('json'),
            ]);

            if ($configureExitCode !== self::SUCCESS) {
                return $this->renderResult(false, 'configuration_failed', $plan);
            }

            if ($writeEnvironment) {
                $environmentResult = $environment->write(
                    path: $this->option('env-path') ?: base_path('.env'),
                    examplePath: $this->option('env-example-path') ?: base_path('.env.example'),
                    values: [
                        'APP_NAME' => $name,
                        'APP_ENV' => 'local',
                        'APP_DEBUG' => 'true',
                        'APP_URL' => $url,
                        'XCHANGE_DEPLOYMENT_PROFILE' => $profile,
                        'XCHANGE_SYSTEM_USER_COLUMN' => 'email',
                        'XCHANGE_SYSTEM_USER_ID' => $systemEmail,
                        'XCHANGE_MOBILE_VERIFICATION_ENABLED' => 'false',
                        'XCHANGE_ONBOARDING_REQUIRE_OTP' => 'false',
                        'XCHANGE_ONBOARDING_REQUIRE_PIN_SETUP' => 'false',
                    ],
                );
                $plan['environment_result'] = $environmentResult;
            }

            config()->set([
                'app.name' => $name,
                'app.url' => $url,
                'x-change.deployment.profile' => $profile,
                'x-change.deployment.profile_explicitly_configured' => true,
                'x-change.payout.system_user_column' => 'email',
                'x-change.payout.system_user_id' => $systemEmail,
            ]);

            $adoptionExitCode = $this->call('x-change:host:adopt', [
                '--json' => (bool) $this->option('json'),
            ]);

            if ($adoptionExitCode !== self::SUCCESS) {
                return $this->renderResult(false, 'host_adoption_failed', $plan);
            }

            $installCommand = [
                PHP_BINARY,
                'artisan',
                'x-change:install',
                "--profile={$profile}",
                '--provision-system-principal',
                "--system-principal-name={$systemName}",
                "--system-principal-email={$systemEmail}",
                '--confirm-system-principal',
                '--no-interaction',
                ...((bool) $this->option('force') ? ['--force'] : []),
                ...((bool) $this->option('no-treasury') ? ['--no-treasury'] : []),
            ];
            $installResult = Process::path(base_path())
                ->timeout(900)
                ->idleTimeout(120)
                ->run($installCommand, function (string $type, string $output): void {
                    if (! $this->option('json')) {
                        $this->output->write($output);
                    }
                });

            if (! $installResult->successful()) {
                return $this->renderResult(false, 'installation_failed', [
                    ...$plan,
                    'install_exit_code' => $installResult->exitCode(),
                ]);
            }

            if (! (bool) $this->option('no-frontend')) {
                foreach ([
                    $frontendDependencies->npmInstallCommand(),
                    ['npm', 'run', 'build'],
                ] as $command) {
                    $result = Process::path(base_path())
                        ->timeout(900)
                        ->idleTimeout(120)
                        ->run($command, function (string $type, string $output): void {
                            if (! $this->option('json')) {
                                $this->output->write($output);
                            }
                        });

                    if (! $result->successful()) {
                        return $this->renderResult(false, 'frontend_build_failed', [
                            ...$plan,
                            'failed_operation' => implode(' ', $command),
                        ]);
                    }
                }
            }

            $manifestExitCode = $this->call('x-change:deployment:generate', [
                '--target' => $target,
                '--profile' => $profile,
                '--path' => $this->option('manifest-path') ?: base_path('x-change.deployment.yaml'),
                '--json' => (bool) $this->option('json'),
            ]);

            if ($manifestExitCode !== self::SUCCESS) {
                return $this->renderResult(false, 'manifest_failed', $plan);
            }

            $doctorExitCode = $this->call('x-change:doctor', [
                '--strict' => true,
            ]);

            if ($doctorExitCode !== self::SUCCESS) {
                return $this->renderResult(false, 'verification_failed', $plan);
            }
        } catch (Throwable $exception) {
            return $this->renderResult(false, $exception->getMessage(), $plan);
        }

        if ($interactive) {
            outro("{$name} is ready · {$url}");
        }

        return $this->renderResult(true, 'ready', $plan);
    }

    /**
     * @param  array<string, mixed>  $plan
     */
    private function renderResult(bool $success, string $status, array $plan): int
    {
        if ($this->option('json')) {
            $this->line(json_encode([
                'schema' => 'x-change.setup.v1',
                'success' => $success,
                'status' => $status,
                ...$plan,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } elseif (! $success) {
            $this->components->error($status);
        } elseif ($status === 'planned') {
            $this->components->info('X-Change setup plan is ready; no changes were made.');
        }

        return $success ? self::SUCCESS : self::FAILURE;
    }
}
