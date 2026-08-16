<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Commercial;

use LBHurtado\XChange\Exceptions\CommercialSaleConflict;
use LBHurtado\XCommerce\Data\CommercialTaxProfileData;
use Throwable;

final class CommercialTaxProfileRegistry
{
    public function resolve(string $reference): CommercialTaxProfileData
    {
        $profile = config('x-change.commercial.tax_profiles.profiles.'.trim($reference));

        if (! is_array($profile)) {
            throw new CommercialSaleConflict("Commercial Tax Profile [{$reference}] is not governed or active.");
        }

        try {
            return CommercialTaxProfileData::fromArray([
                'reference' => trim($reference),
                ...$profile,
            ]);
        } catch (Throwable $exception) {
            throw new CommercialSaleConflict(
                "Commercial Tax Profile [{$reference}] is malformed.",
                previous: $exception,
            );
        }
    }
}
