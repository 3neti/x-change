<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Treasury;

use Spatie\LaravelData\Data;

final class VerifiedTreasuryFundingAllocationData extends Data
{
    public function __construct(
        public readonly string $sourcePositionReference,
        public readonly string $destinationPositionReference,
        public readonly string $recognitionOperationReference,
        public readonly string $allocationOperationReference,
        public readonly int $amountMinor,
        public readonly string $currency,
        public readonly int $destinationTransactionId,
        public readonly string $destinationTransactionUuid,
        public readonly int $transferId,
        public readonly string $transferUuid,
    ) {}
}
