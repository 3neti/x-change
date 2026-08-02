<?php

declare(strict_types=1);

use Illuminate\Support\ServiceProvider;
use LBHurtado\XChange\Contracts\Publication\XChangePublicationContributor;
use LBHurtado\XChange\Data\Publication\PublicationDefinitionData;
use LBHurtado\XChange\Enums\PublicationInvocation;
use LBHurtado\XChange\Enums\PublicationOverwritePolicy;
use LBHurtado\XChange\Enums\PublicationScope;
use LBHurtado\XChange\Services\Publication\PublicationCatalog;
use LBHurtado\XChange\Services\PublishedAssetDriftDetector;

it('renders a deterministic build publication plan without side effects', function (): void {
    app()->instance(PublicationCatalog::class, new PublicationCatalog([
        new class implements XChangePublicationContributor
        {
            public function publications(): iterable
            {
                yield new PublicationDefinitionData(
                    id: 'test.ui',
                    owner: '3neti/test',
                    scope: PublicationScope::Build,
                    invocation: PublicationInvocation::Tag,
                    target: 'test-ui',
                    overwritePolicy: PublicationOverwritePolicy::AlwaysGenerated,
                    description: 'Test UI.',
                    generated: true,
                );
            }
        },
    ]));

    $this->artisan('x-change:publish', [
        '--scope' => 'build',
        '--force' => true,
        '--dry-run' => true,
        '--json' => true,
    ])->expectsOutputToContain('"target":"test-ui","status":"would_publish"')
        ->assertSuccessful();
});

it('rejects unknown publication scopes', function (): void {
    $this->artisan('x-change:publish', [
        '--scope' => 'everything',
        '--json' => true,
    ])->expectsOutputToContain('Publication scope must be build, install, or advanced')
        ->assertFailed();
});

it('does not stamp cockpit files when a different build publication is selected', function (): void {
    $source = sys_get_temp_dir().'/x-change-selected-publication-'.bin2hex(random_bytes(6)).'/source';
    $target = sys_get_temp_dir().'/x-change-selected-publication-'.bin2hex(random_bytes(6)).'/target';
    mkdir($source, 0777, true);
    file_put_contents($source.'/Generated.php', '<?php return true;');

    $provider = new class(app()) extends ServiceProvider
    {
        /** @param array<string, string> $paths */
        public function registerPublications(array $paths): void
        {
            $this->publishes($paths, 'selected-build-publication');
        }
    };
    $provider->registerPublications([$source => $target]);

    app()->instance(PublicationCatalog::class, new PublicationCatalog([
        new class implements XChangePublicationContributor
        {
            public function publications(): iterable
            {
                yield new PublicationDefinitionData(
                    id: 'test.generated',
                    owner: '3neti/test',
                    scope: PublicationScope::Build,
                    invocation: PublicationInvocation::Tag,
                    target: 'selected-build-publication',
                    overwritePolicy: PublicationOverwritePolicy::AlwaysGenerated,
                    description: 'Selected generated input.',
                    generated: true,
                );
            }
        },
    ]));

    $detector = new class extends PublishedAssetDriftDetector
    {
        public bool $headersApplied = false;

        public function applyGeneratedHeaders(?array $mappings = null): array
        {
            $this->headersApplied = true;

            return parent::applyGeneratedHeaders($mappings);
        }
    };
    app()->instance(PublishedAssetDriftDetector::class, $detector);

    $this->artisan('x-change:publish', [
        '--scope' => 'build',
        '--force' => true,
        '--only' => ['test.generated'],
        '--json' => true,
    ])->assertSuccessful();

    expect($detector->headersApplied)->toBeFalse()
        ->and($target.'/Generated.php')->toBeFile();
});
