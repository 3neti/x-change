<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Cockpit;

use Spatie\LaravelData\Data;

class CockpitVoucherReadModelData extends Data
{
    /**
     * @param  array<string, mixed>  $summary
     * @param  array<int, CockpitVoucherEvidenceSummaryData>  $evidence_summary
     * @param  array<string, mixed>  $distribution_links
     * @param  array<string, mixed>  $redactions
     */
    public function __construct(
        public readonly ?string $code,
        public readonly string $status,
        public readonly array $summary = [],
        public readonly array $evidence_summary = [],
        public readonly array $distribution_links = [],
        public readonly array $redactions = ['payloads' => 'not-loaded'],
        public readonly bool $authorized = false,
    ) {}
}
