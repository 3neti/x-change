<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Console\Commands;

use Illuminate\Console\Command;
use LBHurtado\XChange\Services\Deployment\DeploymentManifestRepository;
use Throwable;

final class ValidateDeploymentManifestCommand extends Command
{
    protected $signature = 'x-change:deployment:validate
        {--path= : Defaults to x-change.deployment.yaml in the host root}
        {--json : Output JSON}';

    protected $description = 'Validate the x-change deployment manifest and its safety invariants.';

    public function handle(DeploymentManifestRepository $manifests): int
    {
        $path = trim((string) ($this->option('path') ?: base_path(
            'x-change.deployment.yaml',
        )));

        try {
            $manifest = $manifests->read($path);
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
                'target' => $manifest['deployment']['target'],
                'profile' => $manifest['deployment']['profile'],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->components->info("Deployment manifest is valid [{$path}].");
        }

        return self::SUCCESS;
    }
}
