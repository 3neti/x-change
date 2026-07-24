<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Treasury;

final readonly class TreasuryPayCodeSettlementData
{
    public function __construct(
        public string $reservationOperationReference,
        public string $derecognitionOperationReference,
        public string $inventoryAdjustmentOperationReference,
        public int $beneficiaryAmountMinor,
        public int $feeAmountMinor,
        public int $providerOutflowMinor,
        public string $currency,
    ) {}
}
