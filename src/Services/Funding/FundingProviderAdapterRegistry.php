<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Funding;

use LBHurtado\EmiCore\Contracts\FundingProviderAdapter;
use LBHurtado\XChange\Exceptions\FundingProviderUnavailable;
use LogicException;

class FundingProviderAdapterRegistry
{
    /** @var array<string, FundingProviderAdapter> */
    private array $adapters = [];

    /**
     * @param  iterable<FundingProviderAdapter>  $adapters
     */
    public function __construct(iterable $adapters)
    {
        foreach ($adapters as $adapter) {
            $provider = strtolower(trim($adapter->providerCode()));

            if ($provider === '') {
                throw new LogicException('Funding provider adapters must declare a provider code.');
            }

            if (isset($this->adapters[$provider])) {
                throw new LogicException("Multiple funding adapters are registered for [{$provider}].");
            }

            $this->adapters[$provider] = $adapter;
        }
    }

    public function for(string $provider): FundingProviderAdapter
    {
        $provider = strtolower(trim($provider));

        return $this->adapters[$provider]
            ?? throw FundingProviderUnavailable::forProvider($provider);
    }
}
