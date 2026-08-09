<?php

declare(strict_types=1);

use LBHurtado\XChange\Providers\XChangeServiceProvider;
use LBHurtado\XChange\Services\Publication\CorePublicationContributor;

it('ships a canonical traced brand library with verified inventory hashes', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $libraryRoot = $packageRoot.'/resources/assets/images/brand-library';
    $inventory = json_decode(
        file_get_contents($libraryRoot.'/inventory.json') ?: '',
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    expect($inventory['asset_count'] ?? null)->toBe(30)
        ->and($inventory['families'])->toHaveKeys(['x-change', 'g-clef-pulley'])
        ->and($inventory['audit_findings'])->toContain(
            'No original vector source exists for either family, so all geometry here is reconstructed by tracing.',
        );

    foreach ($inventory['assets'] as $asset) {
        $path = $libraryRoot.'/'.$asset['path'];

        expect($path)->toBeFile()
            ->and(hash_file('sha256', $path))->toBe($asset['sha256']);

        if ($asset['format'] !== 'svg') {
            continue;
        }

        $svg = file_get_contents($path);

        expect($svg)->not->toBeFalse()
            ->toContain('<svg')
            ->toContain('<title')
            ->toContain('<desc')
            ->not->toContain('<image')
            ->not->toContain('<script')
            ->not->toMatch('/(?:href|src)=["\']https?:\/\//i');
    }
});

it('publishes genuine vector brand assets without overwriting host-owned favicons', function (): void {
    $provider = file_get_contents(
        (new ReflectionClass(XChangeServiceProvider::class))->getFileName(),
    );
    $favicon = file_get_contents(
        dirname(__DIR__, 3).'/resources/assets/images/brand-library/g-clef-pulley/svg/g-clef-pulley-favicon.svg',
    );

    expect($provider)
        ->toContain('resources/assets/images/brand-library/g-clef-pulley/svg/g-clef-pulley-favicon.svg')
        ->toContain("public_path('vendor/x-change/favicon.svg')")
        ->not->toContain("resources/assets/favicon.svg') => public_path('vendor/x-change/favicon.svg')")
        ->and($favicon)->not->toBeFalse()
        ->toContain('<path')
        ->not->toContain('<image');
});

it('verifies the canonical brand library as part of build publication', function (): void {
    $publication = null;

    foreach ((new CorePublicationContributor)->publications() as $candidate) {
        if ($candidate->id === 'x-change.assets') {
            $publication = $candidate;

            break;
        }
    }

    expect($publication)->not->toBeNull()
        ->and($publication->verificationPaths)->toContain(
            public_path('vendor/x-change/favicon.svg'),
            public_path('vendor/x-change/images/brand-library/inventory.json'),
            public_path('vendor/x-change/images/brand-library/x-change/svg/x-change-logo.svg'),
            public_path('vendor/x-change/images/brand-library/g-clef-pulley/svg/g-clef-pulley-logo.svg'),
        );
});
