<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands;

use Illuminate\Console\Command;
use LBHurtado\XChange\Enums\DeploymentRuntimeTier;
use LBHurtado\XChange\Services\Configuration\DeploymentConfigurationInspector;
use LBHurtado\XChange\Services\Configuration\DeploymentProfileCatalog;
use LBHurtado\XChange\Services\Configuration\ManagedEnvironmentExampleWriter;
use Throwable;

final class ConfigureXChangeCommand extends Command
{
    protected $signature = 'x-change:configure
        {--profile= : development, netbank, paynamics, hybrid, or custom}
        {--runtime-tier= : local, staging, or production}
        {--path= : Environment example path; defaults to the host .env.example}
        {--json : Output JSON}';

    protected $description = 'Generate sanitized x-change environment documentation without writing .env.';

    public function handle(
        DeploymentProfileCatalog $profiles,
        DeploymentConfigurationInspector $inspector,
        ManagedEnvironmentExampleWriter $writer,
    ): int {
        try {
            $profile = $profiles->resolve($this->option('profile'));
            $runtimeTier = DeploymentRuntimeTier::resolve(
                $this->option('runtime-tier')
                    ?: (string) config('x-change.deployment.runtime_tier', 'production'),
            );
            $path = trim((string) ($this->option('path') ?: base_path('.env.example')));
            $writer->write(
                $path,
                $profile->name,
                $profile->providerCodes,
                $runtimeTier->value,
            );
            $result = $inspector->inspect($profile->name, $runtimeTier->value);
        } catch (Throwable $exception) {
            if ($this->option('json')) {
                $this->line(json_encode([
                    'success' => false,
                    'message' => $exception->getMessage(),
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            } else {
                $this->components->error($exception->getMessage());
            }

            return self::FAILURE;
        }

        $payload = [
            'success' => true,
            'example_path' => $path,
            ...$result,
        ];

        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->components->info("Updated sanitized environment example [{$path}].");
        $this->line("Profile: {$profile->name}");
        $this->line("Runtime tier: {$runtimeTier->value}");
        $this->line('Active connections: '.($profile->connectionReferences === []
            ? 'none'
            : implode(', ', $profile->connectionReferences)));

        if ($result['missing_variables'] !== []) {
            $this->components->warn(
                'Configure these deployment variables before installation: '
                .implode(', ', $result['missing_variables']).'.',
            );
        }

        $this->components->warn('.env was not changed. Configure secrets in the deployment environment.');

        return self::SUCCESS;
    }
}
