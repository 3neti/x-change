<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Configuration;

use Illuminate\Filesystem\Filesystem;

final readonly class ManagedEnvironmentExampleWriter
{
    public function __construct(
        private DeploymentEnvironmentCatalog $catalog,
        private ManagedEnvironmentExampleRenderer $renderer,
        private Filesystem $files,
    ) {}

    /**
     * @param  list<string>  $providerCodes
     */
    public function write(
        string $path,
        string $profile,
        array $providerCodes,
        string $runtimeTier = 'local',
    ): void {
        $existing = $this->files->exists($path)
            ? $this->files->get($path)
            : '';

        $this->files->put($path, $this->renderer->render(
            $existing,
            $this->catalog->variables(),
            $profile,
            $providerCodes,
            $runtimeTier,
        ));
    }
}
