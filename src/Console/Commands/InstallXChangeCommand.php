<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands;

use Illuminate\Console\Command;

class InstallXChangeCommand extends Command
{
    protected $signature = 'x-change:install
        {--force : Overwrite existing published files}
        {--no-auth : Skip mobile-first auth scaffold publishing}
        {--no-auth-tests : Skip mobile-first auth test scaffold publishing}
        {--no-settings : Skip mobile-first settings scaffold publishing}
        {--no-settings-tests : Skip mobile-first settings test scaffold publishing}
        {--no-assets : Skip branding asset publishing}
        {--no-handlers : Skip form-flow and handler asset publishing}
        {--no-rider : Skip x-rider asset publishing}
        {--no-migrate : Skip database migrations}';

    protected $description = 'Install the X-Change package UI, assets, and run migrations';

    public function handle(): int
    {
        $this->components->info('Installing X-Change...');

        $force = (bool) $this->option('force');

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

        $this->publishOnboardingAssets($force);

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

        // Run migrations
        if (! $this->option('no-migrate')) {
            $this->components->task('Running migrations', function (): void {
                $this->callSilently('migrate', [
                    '--force' => true,
                ]);
            });
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

    protected function publishOnboardingAssets(bool $force): void
    {
        $provider = 'LBHurtado\\Onboarding\\OnboardingServiceProvider';

        if (! class_exists($provider)) {
            $this->components->warn('3neti/onboarding is not installed; skipping onboarding publish steps.');

            return;
        }

        $this->components->task('Publishing onboarding config', function () use ($force): void {
            $this->callSilently('vendor:publish', [
                '--tag' => 'onboarding-config',
                '--force' => $force,
            ]);
        });

        $this->components->task('Publishing onboarding migrations', function () use ($force): void {
            $this->callSilently('vendor:publish', [
                '--tag' => 'onboarding-migrations',
                '--force' => $force,
            ]);
        });
    }
}
