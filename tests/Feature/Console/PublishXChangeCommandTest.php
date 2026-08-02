<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\Publication\XChangePublicationContributor;
use LBHurtado\XChange\Data\Publication\PublicationDefinitionData;
use LBHurtado\XChange\Enums\PublicationInvocation;
use LBHurtado\XChange\Enums\PublicationOverwritePolicy;
use LBHurtado\XChange\Enums\PublicationScope;
use LBHurtado\XChange\Services\Publication\PublicationCatalog;

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
