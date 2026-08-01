<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands;

use Illuminate\Console\Command;
use LBHurtado\XChange\Services\Deployment\DeploymentManifestGenerator;
use LBHurtado\XChange\Services\Deployment\DeploymentManifestRepository;
use Throwable;

final class GenerateDeploymentManifestCommand extends Command
{
    protected $signature = 'x-change:deployment:generate
        {--target=local : local, laravel-cloud, forge, or custom}
        {--profile= : development, netbank, paynamics, hybrid, or custom}
        {--path= : Defaults to x-change.deployment.yaml in the host root}
        {--json : Output JSON}';

    protected $description = 'Generate a secret-free, AI-readable x-change deployment manifest.';

    public function handle(
        DeploymentManifestGenerator $generator,
        DeploymentManifestRepository $manifests,
    ): int {
        $path = trim((string) ($this->option('path') ?: base_path(
            'x-change.deployment.yaml',
        )));

        try {
            $manifest = $generator->generate(
                target: trim((string) $this->option('target')),
                profileName: $this->option('profile'),
            );
            $manifests->write($path, $manifest);
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

        if ($this->option('json')) {
            $this->line(json_encode([
                'success' => true,
                'path' => $path,
                'manifest' => $manifest,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->components->info("Generated deployment manifest [{$path}].");
            $this->line('No secret values were written.');
        }

        return self::SUCCESS;
    }
}
