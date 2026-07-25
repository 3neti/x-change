<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Funding;

use Spatie\LaravelData\Data;

final class PrepareFundingRequestData extends Data
{
    public function __construct(
        public readonly int $recognizedValueMinor,
        public readonly string $currency,
        public readonly string $connectionReference,
        public readonly string $evidenceReference,
        public readonly string $reviewerType,
        public readonly string $reviewerId,
        public readonly ?string $reviewNotes = null,
    ) {}
}
