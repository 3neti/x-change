<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Settlement;

use Carbon\CarbonImmutable;

final readonly class ProvisionalCoverageTermsData
{
    /**
     * @param  array<string, mixed>  $terms
     * @param  array<string, mixed>  $authorization
     */
    public function __construct(
        public string $driverId,
        public string $driverVersion,
        public string $coverageType,
        public string $currency,
        public CarbonImmutable $effectiveAt,
        public ?CarbonImmutable $expiresAt = null,
        public ?int $coverageAmountMinor = null,
        public array $terms = [],
        public array $authorization = [],
    ) {}
}
