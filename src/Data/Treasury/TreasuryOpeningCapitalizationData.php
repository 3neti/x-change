<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Treasury;

final readonly class TreasuryOpeningCapitalizationData
{
    /**
     * @param  list<TreasuryOpeningCapitalizationConnectionData>  $connections
     */
    public function __construct(
        public array $connections,
    ) {}
}
