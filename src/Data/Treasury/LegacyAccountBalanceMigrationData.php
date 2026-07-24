<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Treasury;

final readonly class LegacyAccountBalanceMigrationData
{
    public function __construct(
        public string $status,
        public string $connectionReference,
        public string $provider,
        public string $currency,
        public int $amountMinor,
        public ?string $sourcePositionReference = null,
        public ?string $destinationPositionReference = null,
        public ?string $allocationOperationReference = null,
        public ?int $legacyDebitTransactionId = null,
        public ?string $legacyDebitTransactionUuid = null,
    ) {}
}
