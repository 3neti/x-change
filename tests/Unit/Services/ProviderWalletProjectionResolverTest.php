<?php

declare(strict_types=1);

use LBHurtado\EmiCore\Contracts\ProviderWalletProjectionSynchronizer;
use LBHurtado\XChange\Services\ProviderWalletProjectionResolver;

it('selects a wallet synchronizer by the provider capability', function (): void {
    $synchronizer = new class implements ProviderWalletProjectionSynchronizer
    {
        public function providerCode(): string
        {
            return 'other';
        }

        public function supportsProvider(string $provider): bool
        {
            return $provider === 'other';
        }

        public function handle(string $walletId, mixed $owner = null): array
        {
            return ['wallet' => $walletId];
        }

        public function configuredWalletIdForOwner(mixed $owner): ?string
        {
            return null;
        }
    };
    app()->instance('test.other-wallet-synchronizer', $synchronizer);
    app()->tag('test.other-wallet-synchronizer', 'emi.provider-wallet-projection-synchronizers');

    expect(app(ProviderWalletProjectionResolver::class)->for('other'))->toBe($synchronizer)
        ->and(app(ProviderWalletProjectionResolver::class)->for('unknown'))->toBeNull();
});
