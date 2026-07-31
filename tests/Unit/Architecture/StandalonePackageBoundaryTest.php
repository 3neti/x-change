<?php

declare(strict_types=1);

it('does not bind runtime code to concrete host application models', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $runtimeFiles = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
        $packageRoot.'/src',
        FilesystemIterator::SKIP_DOTS,
    ));

    foreach ($runtimeFiles as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $source = file_get_contents($file->getPathname());

        expect($source)
            ->not->toMatch('/^use App\\\\/m')
            ->not->toMatch('/\\\\App\\\\Models\\\\[A-Za-z0-9_]+::class/');
    }

    foreach (glob($packageRoot.'/config/*.php') ?: [] as $path) {
        $source = file_get_contents($path);

        expect($source)
            ->not->toMatch('/^use App\\\\/m')
            ->not->toMatch('/\\\\App\\\\Models\\\\[A-Za-z0-9_]+::class/');
    }
});

it('declares only external repositories and tagged package constraints', function (): void {
    $composer = json_decode(
        file_get_contents(dirname(__DIR__, 3).'/composer.json'),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    foreach ($composer['repositories'] ?? [] as $repository) {
        expect($repository)
            ->toHaveKey('type', 'git')
            ->not->toHaveKey('options');
    }

    foreach ($composer['require'] ?? [] as $package => $constraint) {
        if (! str_starts_with($package, '3neti/')) {
            continue;
        }

        expect($constraint)->not->toContain('dev-');
    }
});
