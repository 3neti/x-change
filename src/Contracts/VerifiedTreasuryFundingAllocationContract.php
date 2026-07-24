<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Contracts;

use LBHurtado\XChange\Data\Treasury\VerifiedTreasuryFundingAllocationData;

interface VerifiedTreasuryFundingAllocationContract
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function allocate(
        string $accountReference,
        string $provider,
        int $amountMinor,
        string $currency,
        string $evidenceReference,
        array $metadata = [],
    ): VerifiedTreasuryFundingAllocationData;
}
