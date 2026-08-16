<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Commercial;

use LBHurtado\XCommerce\Data\CommercialOfferingData;
use Spatie\LaravelData\Data;

final class CommercialOfferingManifestData extends Data
{
    public function __construct(
        public string $schema,
        public string $profile,
        public string $hash,
        public string $yaml,
        public CommercialOfferingData $offering,
    ) {}
}
