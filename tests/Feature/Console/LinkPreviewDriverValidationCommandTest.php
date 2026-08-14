<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use LBHurtado\XChange\Services\LinkPreview\LinkPreviewDriverRepository;
use LBHurtado\XChange\Services\LinkPreview\LinkPreviewEngine;

it('validates the package link-preview drivers without network access', function () {
    $exitCode = Artisan::call('x-change:link-preview:validate', ['--json' => true]);
    $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($exitCode)->toBe(0)
        ->and($report['schema'])->toBe('x-change.link-preview-driver-validation.v1')
        ->and($report['valid'])->toBeTrue()
        ->and(collect($report['drivers'])->pluck('key')->all())->toBe([
            'spotify',
            'youtube',
        ])
        ->and(collect($report['drivers'])->every(
            fn (array $driver): bool => $driver['valid'],
        ))->toBeTrue();
});

it('fails closed when a configured driver manifest is invalid', function () {
    $files = app(Filesystem::class);
    $directory = storage_path('framework/testing/link-preview-invalid-'.str()->uuid());
    $files->ensureDirectoryExists($directory);
    $files->put($directory.'/unsafe.yaml', <<<'YAML'
    schema_version: x-change.link-preview-driver.v1
    key: unsafe
    label: Unsafe
    enabled: true
    canonicalization:
      strategy: arbitrary_callback
    match:
      hosts: [127.0.0.1]
      path_pattern: '#^/.*$#'
    metadata:
      strategies: [open_graph]
    artwork:
      hosts: [127.0.0.1]
      mime_types: [image/svg+xml]
    YAML);

    config()->set(
        'x-change.cockpit.quick_generate.url_artwork.driver_directory',
        $directory,
    );
    app()->forgetInstance(LinkPreviewDriverRepository::class);
    app()->forgetInstance(LinkPreviewEngine::class);

    try {
        $exitCode = Artisan::call('x-change:link-preview:validate', ['--json' => true]);
        $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

        expect($exitCode)->toBe(1)
            ->and($report['valid'])->toBeFalse()
            ->and($report['drivers'])->toHaveCount(1)
            ->and($report['drivers'][0]['valid'])->toBeFalse()
            ->and($report['drivers'][0]['error'])->toContain('is not registered');
    } finally {
        $files->deleteDirectory($directory);
    }
});
