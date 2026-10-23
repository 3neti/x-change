<?php

declare(strict_types=1);

use LBHurtado\XChange\Services\Configuration\FrontendRuntimeDependencies;

it('declares the runtime dependencies required by package-owned frontend assets', function () {
    expect(app(FrontendRuntimeDependencies::class)->npmInstallCommand())->toBe([
        'npm',
        'install',
        '--save',
        '@inertiajs/core@^3.6.1',
        '@inertiajs/vue3@^3.6.1',
        '@laravel/echo-vue@^2.4.0',
        '@vueuse/core@^12.8.2',
        'axios@^1.16.0',
        'dompurify@^3.4.2',
        'lucide-vue-next@^0.468.0',
        'marked@^18.0.7',
        'reka-ui@^2.10.1',
        'tesseract.js@^6.0.1',
        'vue@^3.5.40',
    ]);
});

it('detects missing host declarations and lockfile entries', function (): void {
    $hostPath = sys_get_temp_dir().'/x-change-frontend-dependencies-'.bin2hex(random_bytes(6));
    mkdir($hostPath);
    file_put_contents($hostPath.'/package.json', json_encode(['dependencies' => ['vue' => '^3.5.40']]));
    file_put_contents($hostPath.'/package-lock.json', json_encode([
        'packages' => [
            '' => ['dependencies' => ['vue' => '^3.5.40']],
            'node_modules/vue' => ['version' => '3.5.40'],
        ],
    ]));

    $result = app(FrontendRuntimeDependencies::class)->inspectHost($hostPath);

    expect($result['passed'])->toBeFalse()
        ->and($result['missing'])->toContain('tesseract.js', '@inertiajs/core')
        ->and($result['unlocked'])->toContain('tesseract.js', '@inertiajs/core')
        ->and($result['missing'])->not->toContain('vue');
});

it('accepts a host with every requirement declared and locked', function (): void {
    $dependencies = app(FrontendRuntimeDependencies::class);
    $requirements = $dependencies->requirements();
    $hostPath = sys_get_temp_dir().'/x-change-frontend-dependencies-'.bin2hex(random_bytes(6));
    mkdir($hostPath);
    file_put_contents($hostPath.'/package.json', json_encode(['dependencies' => $requirements]));
    file_put_contents($hostPath.'/package-lock.json', json_encode([
        'packages' => [
            '' => ['dependencies' => $requirements],
            ...array_fill_keys(
                array_map(static fn (string $name): string => 'node_modules/'.$name, array_keys($requirements)),
                ['version' => '1.0.0'],
            ),
        ],
    ]));

    expect($dependencies->inspectHost($hostPath))->toBe([
        'passed' => true,
        'missing' => [],
        'unlocked' => [],
    ]);
});

it('covers every direct npm import in package frontend source', function (): void {
    $packagePath = dirname(__DIR__, 4);
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($packagePath.'/resources/js'));
    $imports = [];

    foreach ($files as $file) {
        if (! $file->isFile() || ! preg_match('/\.(vue|ts|tsx|mts|js|jsx|mjs)$/', $file->getFilename())) {
            continue;
        }

        preg_match_all('/\b(?:from\s*|import\s*\(\s*|import\s*)[\'\"]([^\'\"]+)[\'\"]/', (string) file_get_contents($file->getPathname()), $matches);

        foreach ($matches[1] as $specifier) {
            if (str_starts_with($specifier, '.') || str_starts_with($specifier, '/') || str_starts_with($specifier, '@/') || str_starts_with($specifier, '#')) {
                continue;
            }

            $segments = explode('/', $specifier);
            $imports[] = str_starts_with($specifier, '@') ? implode('/', array_slice($segments, 0, 2)) : $segments[0];
        }
    }

    $requirements = app(FrontendRuntimeDependencies::class)->requirements();
    $packageManifest = json_decode((string) file_get_contents($packagePath.'/package.json'), true);

    expect(array_values(array_diff(array_unique($imports), array_keys($requirements))))->toBe([])
        ->and(array_values(array_diff(array_keys($requirements), array_keys([
            ...($packageManifest['dependencies'] ?? []),
            ...($packageManifest['devDependencies'] ?? []),
        ]))))->toBe([]);
});
