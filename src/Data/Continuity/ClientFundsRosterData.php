<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Continuity;

use Carbon\CarbonImmutable;

final readonly class ClientFundsRosterData
{
    /**
     * @param  list<ClientFundsRosterRowData>  $rows
     */
    public function __construct(
        public CarbonImmutable $asOf,
        public ?string $instanceId,
        public string $connectionReference,
        public string $provider,
        public string $currency,
        public int $decimalPlaces,
        public string $authorizationReference,
        public array $rows,
    ) {}
}
