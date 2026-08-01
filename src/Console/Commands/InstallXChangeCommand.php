<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Seeder;
use LBHurtado\XChange\Exceptions\TreasuryConfigurationException;
use LBHurtado\XChange\Services\Configuration\CommissioningManifestRecorder;
use LBHurtado\XChange\Services\Configuration\PreInstallReadinessInspector;
use LBHurtado\XChange\Services\Host\HostApplicationShellAdopter;
use LBHurtado\XChange\Services\PublishedAssetDriftDetector;
use LBHurtado\XChange\Services\Treasury\SystemPrincipalProvisioningService;
use LBHurtado\XChange\Services\Treasury\TreasuryConfigurationValidator;
use LBHurtado\XChange\Services\Treasury\TreasuryInitializationStateService;
use LBHurtado\XChange\Services\Treasury\TreasuryOpeningCapitalizationPolicyResolver;
use LBHurtado\XChange\Services\Treasury\TreasuryPreflightService;
use LBHurtado\XChange\Services\Treasury\TreasuryProviderConnectionCatalog;
use Throwable;

class InstallXChangeCommand extends Command
{
    protected $signature = 'x-change:install
        {--force : Overwrite existing published files}
        {--profile= : Explicit deployment profile for this installation}
        {--no-auth : Skip mobile-first auth scaffold publishing}
        {--no-auth-tests : Skip mobile-first auth test scaffold publishing}
        {--no-settings : Skip mobile-first settings scaffold publishing}
        {--no-settings-tests : Skip mobile-first settings test scaffold publishing}
        {--no-assets : Skip branding asset publishing}
        {--no-handlers : Skip form-flow and handler asset publishing}
        {--no-rider : Skip x-rider asset publishing}
        {--no-x-ray : Skip x-ray asset publishing}
        {--no-migrate : Skip database migrations}
        {--fresh-database : Drop all database tables after live preflight and rebuild them}
        {--confirm-database-reset : Explicitly authorize the destructive fresh database operation}
        {--seeder= : Host bootstrap seeder to run after a fresh database migration}
        {--no-treasury : Explicitly defer Treasury preflight, provisioning, and reconciliation}
        {--treasury-opening-policy= : unattributed, system-capital, or configured}
        {--capitalization-authorization-reference= : Stable deployment or control authorization reference}
        {--confirm-system-ownership : Confirm that opening provider funds belong to the system principal}
        {--provision-system-principal : Create or adopt the configured non-interactive system principal}
        {--system-principal-name= : Display name for a newly created system principal}
        {--system-principal-email= : Email; must match XCHANGE_SYSTEM_USER_ID}
        {--system-principal-authorization-reference= : Optional stable system-principal reference override}
        {--confirm-system-principal : Confirm this Account is the system principal}';

    protected $description = 'Install the X-Change package UI, assets, and run migrations';

    public function handle(
        PublishedAssetDriftDetector $publishedAssets,
        TreasuryConfigurationValidator $treasuryConfiguration,
        TreasuryInitializationStateService $treasuryInitialization,
        TreasuryOpeningCapitalizationPolicyResolver $capitalizationPolicies,
        TreasuryPreflightService $treasuryPreflight,
        TreasuryProviderConnectionCatalog $treasuryConnections,
        PreInstallReadinessInspector $preInstallReadiness,
        SystemPrincipalProvisioningService $systemPrincipalProvisioning,
        CommissioningManifestRecorder $commissioningManifests,
        HostApplicationShellAdopter $hostApplicationShell,
    ): int {
        $this->components->info('Installing X-Change...');

        $capitalizationConnections = [];
        $freshDatabase = (bool) $this->option('fresh-database');
        $initializedConnections = [];
        $liveReadyOpeningConnections = [];
        $openingConnections = [];
        $seeder = trim((string) $this->option('seeder'));

        if ($freshDatabase && (bool) $this->option('no-migrate')) {
            $this->components->error(
                'Fresh database installation cannot be combined with [--no-migrate].',
            );

            return self::FAILURE;
        }

        if ($freshDatabase && (bool) $this->option('no-treasury')) {
            $this->components->error(
                'Fresh database installation cannot be combined with [--no-treasury].',
            );

            return self::FAILURE;
        }

        if ($freshDatabase && ! (bool) $this->option('confirm-database-reset')) {
            $this->components->error(
                'Fresh database installation requires [--confirm-database-reset].',
            );

            return self::FAILURE;
        }

        if (
            $freshDatabase
            && (
                ! app()->environment(['local', 'testing'])
                || ! in_array(
                    mb_strtolower((string) config('app.env')),
                    ['local', 'testing'],
                    true,
                )
            )
        ) {
            $this->components->error(
                'Fresh database installation is limited to local and testing environments.',
            );

            return self::FAILURE;
        }

        if (
            $freshDatabase
            && $seeder === ''
            && ! (bool) $this->option('provision-system-principal')
        ) {
            $this->components->error(
                'Fresh database installation requires either an explicit '
                .'[--seeder] class or [--provision-system-principal].',
            );

            return self::FAILURE;
        }

        if (
            $freshDatabase
            && $seeder !== ''
            && (
                ! class_exists($seeder)
                || ! is_subclass_of($seeder, Seeder::class)
            )
        ) {
            $this->components->error(
                "Fresh database seeder [{$seeder}] must exist and extend "
                .Seeder::class.'.',
            );

            return self::FAILURE;
        }

        if (
            ! $freshDatabase
            && (
                (bool) $this->option('confirm-database-reset')
                || $seeder !== ''
            )
        ) {
            $this->components->error(
                'Database reset options require [--fresh-database].',
            );

            return self::FAILURE;
        }

        if (
            (bool) $this->option('no-treasury')
            && (
                $this->option('treasury-opening-policy') !== null
                || $this->option('capitalization-authorization-reference') !== null
                || (bool) $this->option('confirm-system-ownership')
            )
        ) {
            $this->components->error(
                'Treasury opening capitalization options cannot be combined with [--no-treasury].',
            );

            return self::FAILURE;
        }

        if (
            ! (bool) $this->option('provision-system-principal')
            && (
                $this->option('system-principal-name') !== null
                || $this->option('system-principal-email') !== null
                || $this->option('system-principal-authorization-reference') !== null
                || (bool) $this->option('confirm-system-principal')
            )
        ) {
            $this->components->error(
                'System-principal options require [--provision-system-principal].',
            );

            return self::FAILURE;
        }

        if (
            (bool) $this->option('provision-system-principal')
            && ! (bool) $this->option('confirm-system-principal')
        ) {
            $this->components->error(
                'System-principal provisioning requires [--confirm-system-principal].',
            );

            return self::FAILURE;
        }

        if ((bool) $this->option('provision-system-principal')) {
            try {
                $systemPrincipalProvisioning->assertConfiguration(
                    name: $this->option('system-principal-name'),
                    email: $this->option('system-principal-email'),
                );
            } catch (TreasuryConfigurationException $exception) {
                $this->components->error($exception->getMessage());

                return self::FAILURE;
            }
        }

        if ($this->option('profile') !== null) {
            config()->set('x-change.deployment.profile', $this->option('profile'));
        }

        try {
            $readiness = $preInstallReadiness->inspect();
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        if (! $readiness['ready']) {
            $failedChecks = collect($readiness['checks'])
                ->where('passed', false)
                ->pluck('name')
                ->implode(', ');
            $this->components->error(
                'Pre-install doctor failed: '.$failedChecks.'.',
            );
            if ($readiness['missing_variables'] !== []) {
                $this->components->error(
                    'Missing or invalid environment configuration: '
                    .implode(', ', $readiness['missing_variables']).'.',
                );
            }
            $this->components->warn(
                'Run [php artisan x-change:configure --profile='
                .$readiness['profile'].'] to refresh the sanitized environment checklist, '
                .'then [php artisan x-change:doctor --pre-install --strict].',
            );

            return self::FAILURE;
        }

        if (! $this->option('no-treasury')) {
            try {
                $treasuryConfiguration->assertConfigured();

                if ($freshDatabase) {
                    $openingConnections = array_map(
                        static fn ($connection): string => $connection->reference,
                        $treasuryConnections->active(),
                    );
                } else {
                    $initialization = $treasuryInitialization->inspect();
                    $initializedConnections = $initialization->initialized;
                    $openingConnections = $initialization->uninitialized;

                    if ($initialization->incomplete !== []) {
                        $references = implode(', ', $initialization->incomplete);

                        $this->components->error(
                            "Treasury topology is incomplete or conflicts with configuration [{$references}]. "
                            .'No migrations, Treasury positions, or UI assets were changed.',
                        );

                        return self::FAILURE;
                    }
                }

                $capitalizationConnections = $capitalizationPolicies
                    ->connectionReferences(
                        $this->option('treasury-opening-policy'),
                    );
                $capitalizationConnections = array_values(array_intersect(
                    $capitalizationConnections,
                    $openingConnections,
                ));
            } catch (TreasuryConfigurationException $exception) {
                $this->components->error($exception->getMessage());
                $this->components->warn(
                    'Use [--no-treasury] only when Treasury initialization is intentionally deferred.',
                );

                return self::FAILURE;
            }

            if (
                $capitalizationConnections !== []
                && trim((string) $this->option(
                    'capitalization-authorization-reference',
                )) === ''
            ) {
                $this->components->error(
                    'System-capital opening policy requires [--capitalization-authorization-reference].',
                );

                return self::FAILURE;
            }

            if (
                $capitalizationConnections !== []
                && ! (bool) $this->option('confirm-system-ownership')
            ) {
                $this->components->error(
                    'System-capital opening policy requires [--confirm-system-ownership].',
                );

                return self::FAILURE;
            }

            if ($openingConnections !== []) {
                try {
                    $livePreflight = $treasuryPreflight->run(
                        $openingConnections,
                        live: true,
                    );
                } catch (Throwable) {
                    $this->components->error(
                        'Treasury live preflight could not be completed [provider_unavailable].',
                    );

                    return self::FAILURE;
                }

                foreach ($livePreflight->connections as $connection) {
                    $reference = $connection->connection->reference;

                    if ($connection->ready) {
                        $this->components->info(
                            "Treasury live preflight ready [{$reference}].",
                        );
                        $liveReadyOpeningConnections[] = $reference;

                        continue;
                    }

                    $issues = $connection->issues === []
                        ? 'provider_unavailable'
                        : implode(', ', $connection->issues);
                    $this->components->warn(
                        "Treasury live preflight unavailable [{$reference}]: {$issues}.",
                    );
                }

                if (! $livePreflight->passes()) {
                    $unchangedResources = $freshDatabase
                        ? 'No migrations, Treasury positions, seeders, or UI assets were changed.'
                        : 'No migrations, Treasury positions, or UI assets were changed.';

                    $this->components->error(
                        'Required Treasury provider connections did not pass live preflight. '
                        .$unchangedResources,
                    );

                    return self::FAILURE;
                }
            }

            if ($initializedConnections !== []) {
                foreach ($initializedConnections as $reference) {
                    $this->components->info(
                        "Treasury already initialized [{$reference}]; "
                        .'skipping opening live preflight and reconciliation.',
                    );
                }
            }

            $capitalizationConnections = array_values(array_intersect(
                $capitalizationConnections,
                $liveReadyOpeningConnections,
            ));
        } else {
            $this->components->warn(
                'Treasury initialization is explicitly deferred [--no-treasury]. '
                .'No provider preflight, Treasury positions, or opening reconciliation will run.',
            );
        }

        $force = (bool) $this->option('force');

        if (! $force) {
            try {
                $hostApplicationShell->adopt(commit: false);
            } catch (Throwable $exception) {
                $this->components->error($exception->getMessage());

                return self::FAILURE;
            }
        }

        $publishResources = function () use ($force, $hostApplicationShell, $publishedAssets): void {
            $this->call('vendor:publish', [
                '--tag' => 'x-change-form-flow-drivers',
                '--force' => $this->option('force'),
            ]);

            // Publish UI (pages, components, layouts, composables)
            $this->components->task('Publishing UI files', function () use ($force): void {
                $this->callSilently('vendor:publish', [
                    '--tag' => 'x-change-ui',
                    '--force' => $force,
                ]);
            });

            $this->components->task('Publishing the x-change application shell', function () use ($force, $hostApplicationShell): void {
                if (! $force) {
                    $hostApplicationShell->adopt();
                }

                $this->callSilently('vendor:publish', [
                    '--tag' => 'x-change-shell',
                    '--force' => $force,
                ]);
            });

            $this->components->task('Stamping Cockpit published asset warnings', function () use ($publishedAssets): void {
                $publishedAssets->applyGeneratedHeaders();
            });

            if (! $this->option('no-auth')) {
                $this->components->task('Publishing mobile-first auth scaffold', function () use ($force): void {
                    $this->callSilently('vendor:publish', [
                        '--tag' => 'x-change-auth',
                        '--force' => $force,
                    ]);
                });

                if (! $this->option('no-auth-tests')) {
                    $this->components->task('Publishing mobile-first auth tests', function () use ($force): void {
                        $this->callSilently('vendor:publish', [
                            '--tag' => 'x-change-auth-tests',
                            '--force' => $force,
                        ]);
                    });
                } else {
                    $this->components->warn('Skipping mobile-first auth test scaffold publishing.');
                }
            } else {
                $this->components->warn('Skipping mobile-first auth scaffold publishing.');
            }

            if (! $this->option('no-settings')) {
                $this->components->task('Publishing mobile-first settings scaffold', function () use ($force): void {
                    $this->callSilently('vendor:publish', [
                        '--tag' => 'x-change-settings',
                        '--force' => $force,
                    ]);
                });

                if (! $this->option('no-settings-tests')) {
                    $this->components->task('Publishing mobile-first settings tests', function () use ($force): void {
                        $this->callSilently('vendor:publish', [
                            '--tag' => 'x-change-settings-tests',
                            '--force' => $force,
                        ]);
                    });
                } else {
                    $this->components->warn('Skipping mobile-first settings test scaffold publishing.');
                }
            } else {
                $this->components->warn('Skipping mobile-first settings scaffold publishing.');
            }

            // Publish branding assets
            if (! $this->option('no-assets')) {
                $this->components->task('Publishing branding assets', function () use ($force): void {
                    $this->callSilently('vendor:publish', [
                        '--tag' => 'x-change-assets',
                        '--force' => $force,
                    ]);
                });
            }

            // Publish form-flow and handler assets (if installed)
            if (! $this->option('no-handlers')) {
                $formFlowProviders = [
                    'LBHurtado\FormFlowManager\FormFlowServiceProvider',
                    'LBHurtado\FormHandlerKYC\KYCHandlerServiceProvider',
                    'LBHurtado\FormHandlerLocation\LocationHandlerServiceProvider',
                    'LBHurtado\FormHandlerOtp\OtpHandlerServiceProvider',
                    'LBHurtado\FormHandlerSelfie\SelfieHandlerServiceProvider',
                    'LBHurtado\FormHandlerSignature\SignatureHandlerServiceProvider',
                ];

                foreach ($formFlowProviders as $provider) {
                    if (class_exists($provider)) {
                        $shortName = class_basename($provider);
                        $this->components->task("Publishing {$shortName}", function () use ($provider, $force): void {
                            $this->callSilently('vendor:publish', [
                                '--provider' => $provider,
                                '--force' => $force,
                            ]);
                        });
                    }
                }
            }

            // Publish x-rider UI/components if installed
            if (! $this->option('no-rider')) {
                $provider = 'LBHurtado\\XRider\\XRiderServiceProvider';

                if (class_exists($provider)) {
                    $this->components->task('Publishing x-rider UI files', function () use ($force): void {
                        $this->callSilently('vendor:publish', [
                            '--tag' => 'x-rider-ui',
                            '--force' => $force,
                        ]);
                    });

                    $this->components->task('Publishing x-rider drivers', function () use ($force): void {
                        $this->callSilently('vendor:publish', [
                            '--tag' => 'x-rider-drivers',
                            '--force' => $force,
                        ]);
                    });
                }
            }

            if (! $this->option('no-x-ray')) {
                $provider = 'LBHurtado\\XRay\\XRayServiceProvider';

                if (class_exists($provider)) {
                    $this->components->task('Publishing x-ray config', function () use ($force): void {
                        $this->callSilently('vendor:publish', [
                            '--tag' => 'x-ray-config',
                            '--force' => $force,
                        ]);
                    });

                    $this->components->task('Publishing x-ray UI files', function () use ($force): void {
                        $this->callSilently('vendor:publish', [
                            '--tag' => 'x-ray-assets',
                            '--force' => $force,
                        ]);
                    });
                }
            }
        };

        $this->publishHostMigrations($force);
        $this->publishOnboardingMigrations($force);

        // Run migrations
        if (! $this->option('no-migrate')) {
            $migrationExitCode = self::FAILURE;
            $migrationTask = $freshDatabase
                ? 'Resetting and migrating database'
                : 'Running migrations';

            $this->components->task($migrationTask, function () use (
                $freshDatabase,
                &$migrationExitCode,
            ): bool {
                $migrationExitCode = $this->callSilently(
                    $freshDatabase ? 'migrate:fresh' : 'migrate',
                    ['--force' => true],
                );

                return $migrationExitCode === self::SUCCESS;
            });

            if ($migrationExitCode !== self::SUCCESS) {
                $this->components->error('Database migration failed; X-Change installation is incomplete.');

                return self::FAILURE;
            }
        }

        if ($freshDatabase && $seeder !== '') {
            $seedExitCode = self::FAILURE;
            $this->components->task(
                "Running bootstrap seeder [{$seeder}]",
                function () use ($seeder, &$seedExitCode): bool {
                    $seedExitCode = $this->callSilently('db:seed', [
                        '--class' => $seeder,
                        '--force' => true,
                    ]);

                    return $seedExitCode === self::SUCCESS;
                },
            );

            if ($seedExitCode !== self::SUCCESS) {
                $this->components->error('Bootstrap seeding failed; X-Change installation is incomplete.');

                return self::FAILURE;
            }

        }

        if ($freshDatabase) {
            $initialization = $treasuryInitialization->inspect();

            if (
                $initialization->initialized !== []
                || $initialization->incomplete !== []
                || array_values(array_diff(
                    $openingConnections,
                    $initialization->uninitialized,
                )) !== []
            ) {
                $this->components->error(
                    'Bootstrap seeder created or conflicted with Treasury topology. '
                    .'Fresh installation requires Treasury to begin uninitialized.',
                );

                return self::FAILURE;
            }
        }

        if ((bool) $this->option('provision-system-principal')) {
            $exitCode = $this->call('x-change:system-principal:provision', [
                '--name' => $this->option('system-principal-name'),
                '--email' => $this->option('system-principal-email'),
                '--authorization-reference' => $this->option(
                    'system-principal-authorization-reference',
                ),
                '--commit' => true,
                '--confirm-system-principal' => true,
                '--no-interaction' => true,
            ]);

            if ($exitCode !== self::SUCCESS) {
                $this->components->error(
                    'System-principal provisioning failed; X-Change installation is incomplete.',
                );

                return self::FAILURE;
            }
        }

        if (! $this->option('no-treasury') && $liveReadyOpeningConnections !== []) {
            $exitCode = $this->call('x-change:treasury:provision', [
                '--connection' => $liveReadyOpeningConnections,
                '--no-interaction' => true,
            ]);

            if ($exitCode !== self::SUCCESS) {
                $this->components->error('Treasury provisioning failed; X-Change installation is incomplete.');

                return self::FAILURE;
            }

            $exitCode = $this->call('x-change:treasury:reconcile-opening', [
                '--connection' => $liveReadyOpeningConnections,
                '--no-interaction' => true,
            ]);

            if ($exitCode !== self::SUCCESS) {
                $this->components->error('Treasury opening reconciliation failed; X-Change installation is incomplete.');

                return self::FAILURE;
            }

            if ($capitalizationConnections !== []) {
                $exitCode = $this->call(
                    'x-change:treasury:capitalize-opening',
                    [
                        '--connection' => $capitalizationConnections,
                        '--authorization-reference' => (string) $this->option(
                            'capitalization-authorization-reference',
                        ),
                        '--confirm-system-ownership' => true,
                        '--commit' => true,
                        '--no-interaction' => true,
                    ],
                );

                if ($exitCode !== self::SUCCESS) {
                    $this->components->error(
                        'Treasury opening capitalization failed; X-Change installation is incomplete.',
                    );

                    return self::FAILURE;
                }
            } else {
                $this->components->warn(
                    'Opening provider funds remain Legacy Unattributed; no system Account Funding Reserve was capitalized.',
                );
            }
        } elseif (
            ! $this->option('no-treasury')
            && $openingConnections !== []
        ) {
            $this->components->warn(
                'No Treasury connection passed live preflight; no Treasury positions were provisioned.',
            );
        }

        $publishResources();

        if (! $this->option('no-treasury')) {
            $commissioningManifests->record();
        } else {
            $this->components->warn(
                'Commissioning remains incomplete because Treasury was explicitly deferred.',
            );
        }

        $this->newLine();
        $this->components->info('X-Change installed successfully.');
        $this->newLine();
        $this->components->warn('Next steps:');
        $this->line('  1. Run <comment>npm install</comment>');
        $this->line('  2. Run <comment>npm run build</comment> (or <comment>npm run dev</comment>)');
        $this->newLine();

        return self::SUCCESS;
    }

    protected function publishOnboardingMigrations(bool $force): void
    {
        $provider = 'LBHurtado\\Onboarding\\OnboardingServiceProvider';

        if (! class_exists($provider)) {
            return;
        }

        $this->components->task('Publishing onboarding migrations', function () use ($force): void {
            $this->callSilently('vendor:publish', [
                '--tag' => 'onboarding-migrations',
                '--force' => $force,
            ]);
        });
    }

    protected function publishHostMigrations(bool $force): void
    {
        $this->components->task('Publishing x-change host migrations', function () use ($force): void {
            $this->callSilently('vendor:publish', [
                '--tag' => 'x-change-host-migrations',
                '--force' => $force,
            ]);
        });
    }
}
