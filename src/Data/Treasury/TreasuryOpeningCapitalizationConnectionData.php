<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Treasury;

final readonly class TreasuryOpeningCapitalizationConnectionData
{
    public function __construct(
        public string $connectionReference,
        public string $provider,
        public string $currency,
        public string $status,
        public int $providerBalanceMinor,
        public int $inventoryBalanceMinor,
        public int $positionBalanceMinor,
        public int $capitalizedAmountMinor,
        public int $legacyUnattributedBeforeMinor,
        public int $legacyUnattributedAfterMinor,
        public int $accountFundingReserveBeforeMinor,
        public int $accountFundingReserveAfterMinor,
        public ?string $operationReference = null,
    ) {}
}
