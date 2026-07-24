<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Treasury;

use LBHurtado\XChange\Enums\TreasuryOpeningBalanceStatus;

final readonly class TreasuryOpeningBalanceConnectionData
{
    public function __construct(
        public string $connectionReference,
        public string $provider,
        public string $currency,
        public TreasuryOpeningBalanceStatus $status,
        public int $providerBalanceMinor,
        public int $inventoryBalanceMinor,
        public int $positionBalanceMinor,
        public int $differenceMinor,
        public string $evidenceReference,
        public string $observedAt,
        public ?string $reason = null,
        public ?string $inventoryOperationReference = null,
        public ?string $positionOperationReference = null,
    ) {}
}
