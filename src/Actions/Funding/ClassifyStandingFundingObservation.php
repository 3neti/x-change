<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Actions\Funding;

use LBHurtado\EmiCore\Models\ProviderFundingObservation;
use LBHurtado\XChange\Models\StandingFundingAddress;
use LogicException;

final class ClassifyStandingFundingObservation
{
    public function handle(ProviderFundingObservation $observation): ?StandingFundingAddress
    {
        $fundingAddressHash = $this->fundingAddressHash($observation->funding_address);

        if ($fundingAddressHash === null) {
            return null;
        }

        $addresses = StandingFundingAddress::query()
            ->where('provider_code', strtolower($observation->provider_code))
            ->where('funding_address_hash', $fundingAddressHash)
            ->limit(2)
            ->get();

        if ($addresses->count() > 1) {
            throw new LogicException('A provider destination matches multiple Standing Funding Addresses.');
        }

        return $addresses->first();
    }

    private function fundingAddressHash(?string $fundingAddress): ?string
    {
        if (! is_string($fundingAddress)
            || preg_match('/\Asha256:([a-f0-9]{64})\z/', strtolower($fundingAddress), $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }
}
