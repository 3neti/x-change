<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Claim;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

class ClaimExperienceData extends Data
{
    public function __construct(
        public int $version,
        public array $entry,

        #[DataCollectionOf(ClaimPhaseData::class)]
        public DataCollection $phases,

        public array $consumed,
        public ClaimExperienceDiagnosticsData $diagnostics,
    ) {}
}
