<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Funding;

final class NetbankReusableFundingReceiptHistoryData
{
    /**
     * @param  list<NetbankReusableFundingObservationData>  $observations
     */
    public function __construct(
        public readonly array $observations,
        public readonly ?string $lastCheckedAt,
    ) {}
}
