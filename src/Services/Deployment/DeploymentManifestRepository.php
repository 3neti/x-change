<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Deployment;

use Illuminate\Filesystem\Filesystem;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;

final readonly class DeploymentManifestRepository
{
    public function __construct(
        private Filesystem $files,
        private DeploymentManifestValidator $validator,
    ) {}

    /**
     * @param  array<string, mixed>  $manifest
     */
    public function write(string $path, array $manifest): void
    {
        $this->validator->validate($manifest);
        $directory = dirname($path);

        if (! $this->files->isDirectory($directory)) {
            $this->files->makeDirectory($directory, 0755, true);
        }

        $temporary = $path.'.tmp';
        $contents = Yaml::dump($manifest, 8, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);

        if ($this->files->put($temporary, $contents, true) === false) {
            throw new RuntimeException("Unable to write deployment manifest [{$path}].");
        }

        if (! $this->files->move($temporary, $path)) {
            throw new RuntimeException("Unable to replace deployment manifest [{$path}].");
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function read(string $path): array
    {
        if (! $this->files->exists($path)) {
            throw new RuntimeException("Deployment manifest [{$path}] does not exist.");
        }

        $manifest = Yaml::parseFile($path);

        if (! is_array($manifest)) {
            throw new RuntimeException('Deployment manifest must contain a YAML mapping.');
        }

        $this->validator->validate($manifest);

        return $manifest;
    }
}
