<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\EmiCore\Contracts\ProviderWalletProjectionSynchronizer;
use RuntimeException;

final class ProviderWalletProjectionResolver
{
    public function for(string $provider): ?ProviderWalletProjectionSynchronizer
    {
        $matches = collect(app()->tagged('emi.provider-wallet-projection-synchronizers'))
            ->filter(fn (mixed $candidate): bool => $candidate instanceof ProviderWalletProjectionSynchronizer
                && $candidate->supportsProvider($provider))
            ->uniqueStrict();

        if ($matches->count() > 1) {
            throw new RuntimeException('Multiple wallet projection synchronizers support this provider.');
        }

        return $matches->first();
    }
}
