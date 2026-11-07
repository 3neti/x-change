<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\EmiCore\Contracts\PayoutProviderIdentity;

final class PayoutProviderIdentityMatcher
{
    public function matches(string $expected, string $observed): bool
    {
        $expected = strtolower(trim($expected));
        $observed = strtolower(trim($observed));
        if ($expected === '' || $observed === '') {
            return false;
        }
        if ($expected === $observed) {
            return true;
        }
        $matches = collect(app()->tagged('emi.payout-provider-identities'))
            ->filter(fn (mixed $identity): bool => $identity instanceof PayoutProviderIdentity && $identity->acceptsPayoutProvider($observed))
            ->map(fn (PayoutProviderIdentity $identity): string => strtolower(trim($identity->providerCode())))
            ->unique()->values();

        return $matches->count() === 1 && $matches->first() === $expected;
    }
}
