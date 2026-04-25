<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services;

use LBHurtado\EmiCore\Data\PayoutRequestData;
use LBHurtado\EmiCore\Enums\SettlementRail;
use LBHurtado\MoneyIssuer\Support\BankRegistry;
use RuntimeException;

class WithdrawalRailGuard
{
    public function __construct(
        protected BankRegistry $bankRegistry,
    ) {}

    public function assertAllowed(PayoutRequestData $input): void
    {
        $rail = SettlementRail::from($input->settlement_rail);

        if ($rail === SettlementRail::PESONET && $this->bankRegistry->isEMI($input->bank_code)) {
            $bankName = $this->bankRegistry->getBankName($input->bank_code);

            throw new RuntimeException(
                "Cannot disburse to {$bankName} via PESONET. E-money institutions require INSTAPAY."
            );
        }
    }
}
