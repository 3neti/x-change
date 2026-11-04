<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Funding;

use LBHurtado\EmiCore\Contracts\DirectQrFundingInstructionIssuer;
use LBHurtado\EmiCore\Contracts\OnDemandFundingInstructionProvider;
use LBHurtado\EmiCore\Data\Funding\FundingInstructionRequestData;
use LBHurtado\EmiCore\Data\Funding\FundingInstructionsData;
use LBHurtado\XChange\Models\FundingIntent;

final readonly class OnDemandFundingInstructionIssuer
{
    public function __construct(
        private FundingProviderAdapterRegistry $providers,
    ) {}

    public function create(
        FundingIntent $intent,
        FundingInstructionRequestData $request,
    ): FundingInstructionsData {
        $provider = $this->providers->for($intent->provider_code);
        if ($provider instanceof OnDemandFundingInstructionProvider) {
            return $provider->createOnDemandFundingInstructions(
                $request,
                (bool) config('x-change.issuance_funding.on_demand.fixed_qr_ph.enabled', false),
            );
        }

        if ($provider instanceof DirectQrFundingInstructionIssuer) {
            throw new \RuntimeException('The provider must support on-demand instruction selection.');
        }

        return $provider->createFundingInstructions($request);
    }
}
