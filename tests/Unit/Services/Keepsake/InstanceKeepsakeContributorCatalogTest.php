<?php

declare(strict_types=1);

use LBHurtado\XChange\Contracts\Keepsake\InstanceKeepsakeContributor;
use LBHurtado\XChange\Data\Keepsake\InstanceKeepsakeContext;
use LBHurtado\XChange\Data\Keepsake\InstanceKeepsakeContribution;
use LBHurtado\XChange\Services\Keepsake\InstanceKeepsakeContributorCatalog;

function keepsakeContributor(string $key): InstanceKeepsakeContributor
{
    return new class($key) implements InstanceKeepsakeContributor
    {
        public function __construct(private readonly string $id) {}

        public function key(): string
        {
            return $this->id;
        }

        public function snapshotSchemaVersion(): int
        {
            return 1;
        }

        public function blueprintSchemaVersion(): ?int
        {
            return null;
        }

        public function contribute(InstanceKeepsakeContext $context): InstanceKeepsakeContribution
        {
            return new InstanceKeepsakeContribution($this->id, 1, null);
        }
    };
}

it('sorts contributors deterministically', function () {
    $catalog = new InstanceKeepsakeContributorCatalog([
        keepsakeContributor('pay-codes'),
        keepsakeContributor('accounts'),
    ]);

    expect(array_map(
        static fn (InstanceKeepsakeContributor $contributor): string => $contributor->key(),
        $catalog->contributors(),
    ))->toBe(['accounts', 'pay-codes']);
});

it('rejects duplicate contributor keys', function () {
    $catalog = new InstanceKeepsakeContributorCatalog([
        keepsakeContributor('accounts'),
        keepsakeContributor('accounts'),
    ]);

    expect(fn () => $catalog->contributors())
        ->toThrow(InvalidArgumentException::class, 'Duplicate instance keepsake contributor key');
});
