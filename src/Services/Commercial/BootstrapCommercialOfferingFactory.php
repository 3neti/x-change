<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Commercial;

use LBHurtado\XCommerce\Data\CommercialAttributionPolicyData;
use LBHurtado\XCommerce\Data\CommercialCatalogData;
use LBHurtado\XCommerce\Data\CommercialLegalTraceData;
use LBHurtado\XCommerce\Data\CommercialOfferingData;
use LBHurtado\XCommerce\Data\CommercialWaterfallPolicyData;

final class BootstrapCommercialOfferingFactory
{
    public function make(string $profile): CommercialOfferingData
    {
        $waterfall = match ($profile) {
            'pay_code' => (array) config('x-change.commercial.pay_code.waterfall', []),
            'account_funding' => (array) config('x-change.commercial.pay_code.account_funding_waterfall', []),
            default => throw new \InvalidArgumentException("Unknown commercial profile [{$profile}]."),
        };

        $catalog = CommercialCatalogData::fromArray(
            (array) config('x-commerce.catalogs.pay_code', []),
        );

        return new CommercialOfferingData(
            reference: "commercial-offering:{$profile}",
            version: 1,
            catalog: $catalog,
            waterfallPolicy: CommercialWaterfallPolicyData::fromArray($waterfall),
            attributionPolicy: CommercialAttributionPolicyData::fromArray(
                (array) config('x-change.commercial.attribution_policy', []),
            ),
            legalTrace: CommercialLegalTraceData::fromArray(
                (array) config('x-change.commercial.legal_trace', []),
            ),
            effectiveAt: '1970-01-01T00:00:00+00:00',
        );
    }
}
