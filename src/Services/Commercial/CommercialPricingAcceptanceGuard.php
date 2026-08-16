<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Commercial;

use LBHurtado\XChange\Data\PricingEstimateData;
use LBHurtado\XChange\Exceptions\CommercialPricingChanged;
use LBHurtado\XCommerce\Data\CommercialQuoteData;

final class CommercialPricingAcceptanceGuard
{
    /** @param array<string, mixed> $expected */
    public function assertExpected(array $expected, PricingEstimateData $estimate): void
    {
        if (($expected['offering_reference'] ?? null) !== $estimate->commercial_offering_reference
            || (int) ($expected['offering_version'] ?? 0) !== $estimate->commercial_offering_version
            || ($expected['offering_snapshot_hash'] ?? null) !== $estimate->commercial_offering_snapshot_hash) {
            throw new CommercialPricingChanged(
                'The accepted Commercial Offering is no longer active. Refresh the estimate and issue again.',
            );
        }
    }

    public function assertQuote(PricingEstimateData $estimate, CommercialQuoteData $quote): void
    {
        $offering = $quote->offeringSnapshot;

        if ($offering === null
            || $estimate->commercial_offering_reference !== $offering->reference
            || $estimate->commercial_offering_version !== $offering->version
            || $estimate->commercial_offering_snapshot_hash !== $offering->snapshotHash()
            || (int) round($estimate->total * 100) !== $quote->totalPriceMinor) {
            throw new CommercialPricingChanged(
                'Commercial pricing changed before issuance completed. Refresh the estimate and issue again.',
            );
        }
    }
}
