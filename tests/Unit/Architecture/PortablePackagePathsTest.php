<?php

declare(strict_types=1);

it('keeps tracked package paths portable across Composer archive extractors', function (): void {
    $root = dirname(__DIR__, 3);
    $files = new RecursiveIteratorIterator(
        new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            static fn (SplFileInfo $file): bool => ! in_array(
                $file->getFilename(),
                ['.git', 'node_modules', 'vendor'],
                true,
            ),
        ),
    );

    $invalid = [];

    foreach ($files as $file) {
        $path = str_replace($root.'/', '', $file->getPathname());

        if (preg_match('/[^\x20-\x7E]/', $path) === 1 || str_contains($path, '?')) {
            $invalid[] = $path;
        }
    }

    expect($invalid)->toBe([]);
});
