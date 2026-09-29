<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Funding;

use InvalidArgumentException;
use LBHurtado\EmiCore\Data\Funding\FundingInstructionRequestData;
use LBHurtado\EmiCore\Data\Funding\FundingInstructionsData;
use LBHurtado\XChange\Models\FundingIntent;

final readonly class OnDemandFundingInstructionIssuer
{
    public function __construct(
        private FundingProviderAdapterRegistry $providers,
        private StandingBankTransferFundingInstructionIssuer $standingBankTransfers,
        private NetbankDirectQrFundingInstructionIssuer $directNetbankQr,
    ) {}

    public function create(
        FundingIntent $intent,
        FundingInstructionRequestData $request,
    ): FundingInstructionsData {
        if ($intent->provider_code !== 'netbank') {
            return $this->providers->for($intent->provider_code)->createFundingInstructions($request);
        }

        if (! (bool) config('x-change.issuance_funding.on_demand.fixed_qr_ph.enabled', false)) {
            return $this->standingBankTransfers->create($request);
        }

        return match ($this->netbankQrMode()) {
            'direct_qr' => $this->directNetbankQr->create($request),
            'registered_vca' => $this->providers->for('netbank')->createFundingInstructions($request),
        };
    }

    private function netbankQrMode(): string
    {
        $mode = strtolower(trim((string) config(
            'x-change.issuance_funding.on_demand.fixed_qr_ph.netbank_mode',
            'direct_qr',
        )));

        if (! in_array($mode, ['direct_qr', 'registered_vca'], true)) {
            throw new InvalidArgumentException("Unsupported NetBank on-demand QR mode [{$mode}].");
        }

        return $mode;
    }
}
