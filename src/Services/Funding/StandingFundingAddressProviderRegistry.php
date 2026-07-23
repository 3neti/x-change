<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Funding;

use LBHurtado\EmiCore\Contracts\StandingFundingAddressProvider;
use LBHurtado\XChange\Exceptions\FundingProviderUnavailable;
use LogicException;

final class StandingFundingAddressProviderRegistry
{
    /** @var array<string, StandingFundingAddressProvider> */
    private array $providers = [];

    /**
     * @param  iterable<StandingFundingAddressProvider>  $providers
     */
    public function __construct(iterable $providers)
    {
        foreach ($providers as $provider) {
            $code = strtolower(trim($provider->providerCode()));

            if ($code === '') {
                throw new LogicException('Standing Funding Address providers must declare a provider code.');
            }

            if (isset($this->providers[$code])) {
                throw new LogicException("Multiple Standing Funding Address providers are registered for [{$code}].");
            }

            $this->providers[$code] = $provider;
        }
    }

    public function for(string $provider): StandingFundingAddressProvider
    {
        $provider = strtolower(trim($provider));

        return $this->providers[$provider]
            ?? throw FundingProviderUnavailable::forProvider($provider);
    }
}
