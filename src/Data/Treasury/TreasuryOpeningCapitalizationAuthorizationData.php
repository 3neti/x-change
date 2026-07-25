<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Treasury;

final readonly class TreasuryOpeningCapitalizationAuthorizationData
{
    public function __construct(
        public string $connectionReference,
        public int $amountMinor,
        public string $currency,
        public string $authorizationReference,
        public bool $systemOwnershipConfirmed,
        public bool $commit,
    ) {}
}
