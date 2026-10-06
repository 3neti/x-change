<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Funding;

final readonly class StandingFundingAdmissionData
{
    public function __construct(
        public bool $admitted,
        public string $reason,
        public ?string $runReference = null,
        public ?string $leaseToken = null,
        public ?int $generation = null,
    ) {}
}
