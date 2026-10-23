<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Configuration;

final readonly class FrontendRuntimeDependencies
{
    /** @return array<string, string> */
    public function requirements(): array
    {
        return [
            '@inertiajs/core' => '^3.6.1',
            '@inertiajs/vue3' => '^3.6.1',
            '@laravel/echo-vue' => '^2.4.0',
            '@vueuse/core' => '^12.8.2',
            'axios' => '^1.16.0',
            'dompurify' => '^3.4.2',
            'lucide-vue-next' => '^0.468.0',
            'marked' => '^18.0.7',
            'reka-ui' => '^2.10.1',
            'tesseract.js' => '^6.0.1',
            'vue' => '^3.5.40',
        ];
    }

    /**
     * @return list<string>
     */
    public function npmInstallCommand(): array
    {
        return [
            'npm',
            'install',
            '--save',
            ...array_map(
                static fn (string $name, string $version): string => $name.'@'.$version,
                array_keys($this->requirements()),
                array_values($this->requirements()),
            ),
        ];
    }

    /** @return array{passed: bool, missing: list<string>, unlocked: list<string>} */
    public function inspectHost(string $hostPath): array
    {
        $manifest = $this->readJson($hostPath.'/package.json');
        $lockfile = $this->readJson($hostPath.'/package-lock.json');
        $declared = [
            ...($manifest['dependencies'] ?? []),
            ...($manifest['devDependencies'] ?? []),
        ];
        $locked = [
            ...($lockfile['packages']['']['dependencies'] ?? []),
            ...($lockfile['packages']['']['devDependencies'] ?? []),
        ];

        $missing = [];
        $unlocked = [];

        foreach (array_keys($this->requirements()) as $name) {
            if (! isset($declared[$name])) {
                $missing[] = $name;
            }

            if (! isset($locked[$name], $lockfile['packages']['node_modules/'.$name])) {
                $unlocked[] = $name;
            }
        }

        return [
            'passed' => $missing === [] && $unlocked === [],
            'missing' => $missing,
            'unlocked' => $unlocked,
        ];
    }

    /** @return array<string, mixed> */
    private function readJson(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }
}
