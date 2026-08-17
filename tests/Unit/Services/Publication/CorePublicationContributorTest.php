<?php

declare(strict_types=1);

use LBHurtado\XChange\Enums\PublicationInvocation;
use LBHurtado\XChange\Enums\PublicationOverwritePolicy;
use LBHurtado\XChange\Enums\PublicationScope;
use LBHurtado\XChange\Services\Publication\CorePublicationContributor;
use LBHurtado\XChange\Services\Publication\PublicationCatalog;

it('classifies the complete package publication inventory by ownership boundary', function (): void {
    $catalog = new PublicationCatalog([new CorePublicationContributor]);
    $build = $catalog->definitions(PublicationScope::Build);
    $install = $catalog->definitions(PublicationScope::Install);
    $advanced = $catalog->definitions(PublicationScope::Advanced);

    expect(array_column($build, 'id'))
        ->toContain(
            'x-change.ui',
            'x-change.assets',
            'x-change.form-flow-driver',
            'x-change.link-preview-drivers',
            'x-change.envelope-driver',
            'form-flow.drivers',
            'form-flow.views',
            'form-handler.kyc.ui',
            'form-handler.location.ui',
            'form-handler.otp.ui',
            'form-handler.selfie.ui',
            'form-handler.signature.ui',
            'x-rider.drivers',
            'x-rider.ui',
            'x-ray.ui',
        )
        ->and(array_column($install, 'id'))
        ->toContain(
            'x-change.shell',
            'x-change.auth',
            'x-change.settings',
            'x-change.host-migrations',
            'onboarding.migrations',
        )
        ->and(array_column($advanced, 'id'))
        ->toContain(
            'x-change.config',
            'form-flow.config',
            'form-handler.otp.config',
            'x-rider.config',
            'x-ray.config',
            'onboarding.config',
        );
});

it('keeps build publication granular, generated, and safely replaceable', function (): void {
    $build = (new PublicationCatalog([new CorePublicationContributor]))
        ->definitions(PublicationScope::Build);

    expect($build)->not->toBeEmpty();

    foreach ($build as $definition) {
        expect($definition->invocation)->toBe(PublicationInvocation::Tag)
            ->and($definition->generated)->toBeTrue()
            ->and($definition->overwritePolicy)->toBe(PublicationOverwritePolicy::AlwaysGenerated)
            ->and($definition->verificationPaths)->not->toBeEmpty();
    }
});

it('publishes every packaged link-preview driver as a build input', function (): void {
    $build = (new PublicationCatalog([new CorePublicationContributor]))
        ->definitions(PublicationScope::Build);
    $definition = collect($build)->firstWhere('id', 'x-change.link-preview-drivers');
    $packagedDrivers = collect(glob(dirname(__DIR__, 4).'/config/link-preview-drivers/*.yaml'))
        ->map(fn (string $path): string => basename($path))
        ->sort()
        ->values()
        ->all();
    $publishedDrivers = collect($definition?->verificationPaths ?? [])
        ->map(fn (string $path): string => basename($path))
        ->sort()
        ->values()
        ->all();

    expect($definition)->not->toBeNull()
        ->and($publishedDrivers)->toBe($packagedDrivers);
});

it('publishes the versioned experience registry and theme tokens as build inputs', function (): void {
    $build = (new PublicationCatalog([new CorePublicationContributor]))
        ->definitions(PublicationScope::Build);
    $definition = collect($build)->firstWhere('id', 'x-change.ui');

    expect($definition?->verificationPaths ?? [])->toContain(
        resource_path('js/experience/themes.ts'),
        resource_path('js/experience/themes.css'),
    );
});

it('keeps configuration overrides out of automatic build publication', function (): void {
    $catalog = new PublicationCatalog([new CorePublicationContributor]);
    $buildTargets = array_column($catalog->definitions(PublicationScope::Build), 'target');
    $advancedTargets = array_column($catalog->definitions(PublicationScope::Advanced), 'target');

    expect($buildTargets)
        ->not->toContain('x-change-config', 'form-flow-config', 'otp-handler-config', 'x-ray-config')
        ->and($advancedTargets)
        ->toContain('x-change-config', 'form-flow-config', 'otp-handler-config', 'x-ray-config');
});
