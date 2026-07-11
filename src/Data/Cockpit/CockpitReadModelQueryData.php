<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Cockpit;

use Spatie\LaravelData\Data;

class CockpitReadModelQueryData extends Data
{
    /**
     * @param  array<int, string>  $include
     */
    public function __construct(
        public readonly ?string $code = null,
        public readonly ?string $payCodeSearch = null,
        public readonly ?string $payCodeStatus = null,
        public readonly ?string $operatorId = null,
        public readonly array $include = [],
        public readonly ?string $correlationId = null,
        public readonly ?CockpitOperatorIssuanceActivitySearchFilterData $operatorActivityFilters = null,
    ) {}
}
