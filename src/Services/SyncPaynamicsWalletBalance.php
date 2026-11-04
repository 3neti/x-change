<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\EmiCore\Contracts\ProviderWalletProjectionSynchronizer;
use LBHurtado\EmiCore\Enums\ProviderCode;
use RuntimeException;

class SyncPaynamicsWalletBalance implements ProviderWalletProjectionSynchronizer
{
    public function providerCode(): string
    {
        return ProviderCode::PaynamicsConstellation->value;
    }

    public function supportsProvider(string $provider): bool
    {
        return $this->synchronizer()->supportsProvider($provider);
    }

    /** @return array<string, mixed> */
    public function handle(string $walletId, mixed $owner = null): array
    {
        return $this->synchronizer()->handle($walletId, $owner);
    }

    public function configuredWalletIdForOwner(mixed $owner): ?string
    {
        return $this->synchronizer()->configuredWalletIdForOwner($owner);
    }

    private function synchronizer(): ProviderWalletProjectionSynchronizer
    {
        $matches = collect(app()->tagged('emi.provider-wallet-projection-synchronizers'))
            ->filter(fn (mixed $candidate): bool => $candidate instanceof ProviderWalletProjectionSynchronizer
                && $candidate->providerCode() === ProviderCode::PaynamicsConstellation->value)
            ->uniqueStrict();

        if ($matches->count() !== 1) {
            throw new RuntimeException('A unique wallet projection synchronizer is required for the provider.');
        }

        return $matches->first();
    }
}
