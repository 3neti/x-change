<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\EmiCore\Data\PayoutRequestData;
use LBHurtado\EmiCore\Enums\SettlementRail;
use LBHurtado\MoneyIssuer\Support\BankRegistry;
use LBHurtado\XChange\Contracts\SettlementRailCapabilityRegistryContract;
use RuntimeException;

class WithdrawalRailGuard
{
    public function __construct(
        protected BankRegistry $bankRegistry,
        protected SettlementRailCapabilityRegistryContract $capabilities,
    ) {}

    public function assertAllowed(PayoutRequestData $input): void
    {
        $this->capabilities->assertSupports($input);

        $rail = SettlementRail::from($input->settlement_rail);

        if ($rail === SettlementRail::PESONET && $this->bankRegistry->isEMI($input->bank_code)) {
            $bankName = $this->bankRegistry->getBankName($input->bank_code);

            throw new RuntimeException(
                "Cannot disburse to {$bankName} via PESONET. E-money institutions require INSTAPAY."
            );
        }

        if (! $this->bankRegistry->supportsRail($input->bank_code, $rail)) {
            $bankName = $this->bankRegistry->getBankName($input->bank_code);

            throw new RuntimeException(
                "Cannot disburse to {$bankName} via {$rail->value}. The receiving institution does not support that settlement rail."
            );
        }
    }
}
