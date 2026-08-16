<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Commercial;

use LBHurtado\XCommerce\Data\CommercialComponentEconomicsSetData;
use Spatie\LaravelData\Data;

final class CommercialComponentEconomicsManifestData extends Data
{
    public function __construct(
        public string $schema,
        public string $profile,
        public string $offeringReference,
        public int $offeringVersion,
        public string $offeringSnapshotHash,
        public string $offeringManifestHash,
        public string $hash,
        public string $yaml,
        public CommercialComponentEconomicsSetData $componentEconomics,
    ) {}
}
