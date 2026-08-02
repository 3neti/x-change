<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Filesystem\Filesystem;
use LBHurtado\XChange\Contracts\Publication\XChangePublicationContributor;
use LBHurtado\XChange\Data\Publication\PublicationDefinitionData;
use LBHurtado\XChange\Enums\PublicationInvocation;
use LBHurtado\XChange\Enums\PublicationOverwritePolicy;
use LBHurtado\XChange\Enums\PublicationScope;
use LBHurtado\XChange\Services\Publication\PublicationCatalog;
use LBHurtado\XChange\Services\Publication\PublicationPublisher;

it('publishes and verifies an available generated resource', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'xchange-publication-');
    $kernel = Mockery::mock(Kernel::class);
    $kernel->shouldReceive('call')
        ->once()
        ->with('vendor:publish', Mockery::on(fn (array $arguments): bool => $arguments === [
            '--tag' => 'test-build',
            '--force' => true,
            '--no-interaction' => true,
        ]))
        ->andReturn(0);

    try {
        $result = (new PublicationPublisher($kernel, new Filesystem))->publish(
            publicationCatalogFor(publisherDefinition(verificationPaths: [$path])),
            PublicationScope::Build,
            force: true,
            dryRun: false,
            verify: true,
        );

        expect($result['success'])->toBeTrue()
            ->and($result['results'][0]['status'])->toBe('published');
    } finally {
        @unlink($path);
    }
});

it('fails closed before publication when a required contribution is unavailable', function (): void {
    $kernel = Mockery::mock(Kernel::class);
    $kernel->shouldNotReceive('call');

    expect(fn (): array => (new PublicationPublisher($kernel, new Filesystem))->publish(
        publicationCatalogFor(publisherDefinition(available: false)),
        PublicationScope::Build,
        force: true,
        dryRun: false,
        verify: true,
    ))->toThrow(RuntimeException::class, 'Required publication [test.build] is unavailable');
});

it('requires explicit overwrite authorization for generated build inputs', function (): void {
    $kernel = Mockery::mock(Kernel::class);
    $kernel->shouldNotReceive('call');

    expect(fn (): array => (new PublicationPublisher($kernel, new Filesystem))->publish(
        publicationCatalogFor(publisherDefinition()),
        PublicationScope::Build,
        force: false,
        dryRun: true,
        verify: false,
    ))->toThrow(RuntimeException::class, 'requires explicit --force');
});

/** @param list<string> $verificationPaths */
function publisherDefinition(bool $available = true, array $verificationPaths = []): PublicationDefinitionData
{
    return new PublicationDefinitionData(
        id: 'test.build',
        owner: '3neti/test',
        scope: PublicationScope::Build,
        invocation: PublicationInvocation::Tag,
        target: 'test-build',
        overwritePolicy: PublicationOverwritePolicy::AlwaysGenerated,
        description: 'Test generated build input.',
        available: $available,
        generated: true,
        verificationPaths: $verificationPaths,
    );
}

function publicationCatalogFor(PublicationDefinitionData $definition): PublicationCatalog
{
    return new PublicationCatalog([
        new class($definition) implements XChangePublicationContributor
        {
            public function __construct(private readonly PublicationDefinitionData $definition) {}

            public function publications(): iterable
            {
                yield $this->definition;
            }
        },
    ]);
}
