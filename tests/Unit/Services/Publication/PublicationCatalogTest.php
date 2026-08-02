<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\Publication\XChangePublicationContributor;
use LBHurtado\XChange\Data\Publication\PublicationDefinitionData;
use LBHurtado\XChange\Enums\PublicationInvocation;
use LBHurtado\XChange\Enums\PublicationOverwritePolicy;
use LBHurtado\XChange\Enums\PublicationScope;
use LBHurtado\XChange\Services\Publication\PublicationCatalog;

it('orders and filters typed publication contributions deterministically', function (): void {
    $contributor = new class implements XChangePublicationContributor
    {
        public function publications(): iterable
        {
            yield new PublicationDefinitionData(
                id: 'x-change.shell',
                owner: '3neti/x-change',
                scope: PublicationScope::Install,
                invocation: PublicationInvocation::Tag,
                target: 'x-change-shell',
                overwritePolicy: PublicationOverwritePolicy::CreateIfMissing,
                description: 'Application shell integration.',
            );
            yield new PublicationDefinitionData(
                id: 'x-change.ui',
                owner: '3neti/x-change',
                scope: PublicationScope::Build,
                invocation: PublicationInvocation::Tag,
                target: 'x-change-ui',
                overwritePolicy: PublicationOverwritePolicy::AlwaysGenerated,
                description: 'Generated Cockpit build inputs.',
                generated: true,
            );
        }
    };

    $definitions = (new PublicationCatalog([$contributor]))->definitions();
    $build = (new PublicationCatalog([$contributor]))->definitions(PublicationScope::Build);

    expect(array_column($definitions, 'id'))->toBe(['x-change.shell', 'x-change.ui'])
        ->and(array_column($build, 'id'))->toBe(['x-change.ui']);
});

it('rejects duplicate publication IDs and invocations', function (string $conflict): void {
    $contributor = new class($conflict) implements XChangePublicationContributor
    {
        public function __construct(private readonly string $conflict) {}

        public function publications(): iterable
        {
            yield publicationDefinition('one', 'first-tag');
            yield publicationDefinition(
                $this->conflict === 'id' ? 'one' : 'two',
                $this->conflict === 'invocation' ? 'first-tag' : 'second-tag',
            );
        }
    };

    expect(fn (): array => (new PublicationCatalog([$contributor]))->definitions())
        ->toThrow(InvalidArgumentException::class);
})->with(['id', 'invocation']);

it('rejects host-owned or conditionally overwritten build publications', function (bool $generated, PublicationOverwritePolicy $policy): void {
    expect(fn (): PublicationDefinitionData => new PublicationDefinitionData(
        id: 'unsafe.build',
        owner: 'bank/host',
        scope: PublicationScope::Build,
        invocation: PublicationInvocation::Tag,
        target: 'unsafe-build',
        overwritePolicy: $policy,
        description: 'Unsafe build resource.',
        generated: $generated,
    ))->toThrow(InvalidArgumentException::class);
})->with([
    'host owned' => [false, PublicationOverwritePolicy::AlwaysGenerated],
    'not always generated' => [true, PublicationOverwritePolicy::CreateIfMissing],
]);

function publicationDefinition(string $id, string $target): PublicationDefinitionData
{
    return new PublicationDefinitionData(
        id: $id,
        owner: '3neti/test',
        scope: PublicationScope::Install,
        invocation: PublicationInvocation::Tag,
        target: $target,
        overwritePolicy: PublicationOverwritePolicy::CreateIfMissing,
        description: 'Test publication.',
    );
}
