<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Funding;

use LBHurtado\XChange\Enums\PayCodeIssuanceFundingOrderStatus;

final readonly class OnDemandIssuanceMismatchData
{
    /**
     * @param  array<string, bool|int|string|null>  $details
     */
    public function __construct(
        public PayCodeIssuanceFundingOrderStatus $status,
        public string $reasonCode,
        public string $eventType,
        public array $details,
    ) {}
}
