<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Funding;

use Spatie\LaravelData\Data;

final class PayCodeFundingEligibilityData extends Data
{
    public function __construct(
        public readonly bool $eligible,
        public readonly string $status,
        public readonly string $message,
        public readonly ?int $amountMinor = null,
        public readonly ?string $currency = null,
        public readonly ?string $connectionReference = null,
        public readonly ?string $reservationOperationReference = null,
    ) {}
}
