<?php

declare(strict_types=1);

use Illuminate\Support\ServiceProvider;
use LBHurtado\XChange\Contracts\Publication\XChangePublicationContributor;
use LBHurtado\XChange\Data\Publication\PublicationDefinitionData;
use LBHurtado\XChange\Enums\PublicationInvocation;
use LBHurtado\XChange\Enums\PublicationOverwritePolicy;
use LBHurtado\XChange\Enums\PublicationScope;
use LBHurtado\XChange\Services\Publication\PublicationCatalog;
use LBHurtado\XChange\Services\Publication\PublicationVerifier;
use LBHurtado\XChange\Services\PublishedAssetDriftDetector;

function publicationVerifierTempPath(string $suffix): string
{
    return sys_get_temp_dir().'/x-change-publication-verifier-'.bin2hex(random_bytes(6)).'/'.$suffix;
}

it('verifies every source file while ignoring unrelated host files in shared destinations', function (): void {
    $source = publicationVerifierTempPath('source');
    $target = publicationVerifierTempPath('target');
    mkdir($source, 0777, true);
    mkdir($target, 0777, true);
    file_put_contents($source.'/Generated.vue', '<template>Generated</template>');
    file_put_contents($target.'/Generated.vue', '<template>Generated</template>');
    file_put_contents($target.'/HostOwned.vue', '<template>Host</template>');

    $provider = new class(app()) extends ServiceProvider
    {
        /** @param array<string, string> $paths */
        public function registerPublications(array $paths): void
        {
            $this->publishes($paths, 'publication-verifier-test');
        }
    };
    $provider->registerPublications([$source => $target]);

    $catalog = new PublicationCatalog([
        new class implements XChangePublicationContributor
        {
            public function publications(): iterable
            {
                yield new PublicationDefinitionData(
                    id: 'test.generated',
                    owner: '3neti/test',
                    scope: PublicationScope::Build,
                    invocation: PublicationInvocation::Tag,
                    target: 'publication-verifier-test',
                    overwritePolicy: PublicationOverwritePolicy::AlwaysGenerated,
                    description: 'Test generated input.',
                    generated: true,
                );
            }
        },
    ]);

    $verifier = new PublicationVerifier($catalog, new PublishedAssetDriftDetector);
    $synchronized = $verifier->inspectBuild();
    file_put_contents($target.'/Generated.vue', '<template>Stale</template>');
    $stale = $verifier->inspectBuild();

    expect($synchronized['passed'])->toBeTrue()
        ->and($synchronized['summary'])->toMatchArray([
            'resources' => 1,
            'checked' => 1,
            'ok' => 1,
            'stale' => 0,
            'missing' => 0,
        ])
        ->and($synchronized['files'])->toHaveCount(1)
        ->and($stale['passed'])->toBeFalse()
        ->and($stale['summary']['stale'])->toBe(1)
        ->and($stale['message'])->toContain('x-change:publish --scope=build');
});

it('fails closed when a required build publication is unavailable', function (): void {
    $catalog = new PublicationCatalog([
        new class implements XChangePublicationContributor
        {
            public function publications(): iterable
            {
                yield new PublicationDefinitionData(
                    id: 'missing.generated',
                    owner: '3neti/missing',
                    scope: PublicationScope::Build,
                    invocation: PublicationInvocation::Tag,
                    target: 'missing-generated-build-inputs',
                    overwritePolicy: PublicationOverwritePolicy::AlwaysGenerated,
                    description: 'Missing generated input.',
                    available: false,
                    generated: true,
                );
            }
        },
    ]);

    $result = (new PublicationVerifier($catalog, new PublishedAssetDriftDetector))->inspectBuild();

    expect($result['passed'])->toBeFalse()
        ->and($result['summary']['unavailable'])->toBe(1)
        ->and($result['resources'][0]['passed'])->toBeFalse();
});
