<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Funding;

use LBHurtado\EmiCore\Contracts\OnDemandFundingInstructionProvider;
use LBHurtado\EmiCore\Data\Funding\FundingInstructionRequestData;
use LBHurtado\EmiCore\Data\Funding\FundingInstructionsData;
use RuntimeException;

/** Compatibility entry point for callers requesting a standing transfer. */
final readonly class StandingBankTransferFundingInstructionIssuer
{
    public function __construct(private FundingProviderAdapterRegistry $providers) {}

    public function create(FundingInstructionRequestData $request): FundingInstructionsData
    {
        $provider = $this->providers->for($request->provider);
        if (! $provider instanceof OnDemandFundingInstructionProvider) {
            throw new RuntimeException('The provider does not support on-demand funding instructions.');
        }

        return $provider->createOnDemandFundingInstructions($request, false);
    }
}
