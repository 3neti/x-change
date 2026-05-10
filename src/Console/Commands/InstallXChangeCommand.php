<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands;

use Illuminate\Console\Command;

class InstallXChangeCommand extends Command
{
    protected $signature = 'x-change:install
        {--force : Overwrite existing published files}
        {--no-assets : Skip branding asset publishing}
        {--no-handlers : Skip form-flow and handler asset publishing}
        {--no-migrate : Skip database migrations}';

    protected $description = 'Install the X-Change package UI, assets, and run migrations';

    public function handle(): int
    {
        $this->components->info('Installing X-Change...');

        $force = (bool) $this->option('force');

        // Publish UI (pages, components, layouts, composables)
        $this->components->task('Publishing UI files', function () use ($force): void {
            $this->callSilently('vendor:publish', [
                '--tag' => 'x-change-ui',
                '--force' => $force,
            ]);
        });

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
}
