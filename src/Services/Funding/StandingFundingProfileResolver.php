<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Funding;

use Illuminate\Database\Eloquent\Model;
use LBHurtado\EmiCore\Contracts\StandingFundingAddressProfileInspector;
use LBHurtado\EmiCore\Data\Funding\StandingFundingAddressProfileData;
use LBHurtado\EmiCore\Data\Funding\StandingFundingAddressProfileRequestData;
use LBHurtado\XChange\Models\StandingFundingAddress;
use RuntimeException;

final readonly class StandingFundingProfileResolver
{
    public function __construct(private StandingFundingAddressProviderRegistry $providers) {}

    public function forOwner(string $provider, Model $owner, ?StandingFundingAddress $existing = null, bool $validateReadiness = true): StandingFundingAddressProfileData
    {
        $adapter = $this->providers->for($provider);
        if (! $adapter instanceof StandingFundingAddressProfileInspector) {
            throw new RuntimeException('The funding provider does not support standing address profile inspection.');
        }

        return $adapter->inspectStandingFundingAddressProfile(new StandingFundingAddressProfileRequestData(
            mobile: $owner->getAttribute('mobile'),
            mobileVerified: $owner->getAttribute('mobile_verified_at') !== null,
            existingFundingAddress: $existing?->funding_address_ciphertext,
            existingScheme: $existing?->derivation_scheme,
            validateReadiness: $validateReadiness,
        ));
    }
}
