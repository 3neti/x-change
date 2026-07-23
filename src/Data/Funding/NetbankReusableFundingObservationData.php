<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Funding;

use Spatie\LaravelData\Data;

final class NetbankReusableFundingObservationData extends Data
{
    public function __construct(
        public string $reference,
        public int $grossAmountMinor,
        public int $feeAmountMinor,
        public int $netAmountMinor,
        public string $currency,
        public string $providerStatus,
        public ?string $occurredAt,
        public ?string $settledAt,
    ) {}
}
