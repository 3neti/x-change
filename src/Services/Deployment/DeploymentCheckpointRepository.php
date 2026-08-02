<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Deployment;

use Illuminate\Filesystem\Filesystem;
use RuntimeException;

final readonly class DeploymentCheckpointRepository
{
    public function __construct(private Filesystem $files) {}

    public function record(
        string $environment,
        string $recipeHash,
        string $manifestHash,
        string $operation,
        string $outcome,
        ?string $path = null,
    ): void {
        $path ??= $this->defaultPath();
        $records = $this->read($path);
        $records[] = [
            'schema' => 'x-change.cloud-checkpoint.v1',
            'environment' => $environment,
            'recipe_hash' => $recipeHash,
            'manifest_hash' => $manifestHash,
            'operation' => $operation,
            'outcome' => $outcome,
            'recorded_at' => now()->toIso8601String(),
        ];
        $directory = dirname($path);

        if (! $this->files->isDirectory($directory)) {
            $this->files->makeDirectory($directory, 0755, true);
        }

        $temporary = $path.'.tmp';
        $contents = json_encode($records, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;

        if ($this->files->put($temporary, $contents, true) === false || ! $this->files->move($temporary, $path)) {
            throw new RuntimeException('Unable to persist the sanitized Cloud deployment checkpoint.');
        }
    }

    /** @return list<array<string, string>> */
    public function read(?string $path = null): array
    {
        $path ??= $this->defaultPath();

        if (! $this->files->exists($path)) {
            return [];
        }

        $records = json_decode($this->files->get($path), true, flags: JSON_THROW_ON_ERROR);

        return is_array($records) && array_is_list($records) ? $records : [];
    }

    private function defaultPath(): string
    {
        return (string) config(
            'x-change.deployment.cloud_checkpoint_path',
            storage_path('app/x-change/cloud-deployment-checkpoints.json'),
        );
    }
}
