<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Cockpit;

use Spatie\LaravelData\Data;

class CockpitVoucherReadModelData extends Data
{
    /**
     * @param  array<string, mixed>  $summary
     * @param  array<string, mixed>  $overview
     * @param  array<string, mixed>  $instructions
     * @param  array<string, mixed>  $claims
     * @param  array<string, mixed>  $settlement
     * @param  array<string, mixed>  $treasury
     * @param  array<int, CockpitVoucherEvidenceSummaryData>  $evidence_summary
     * @param  array<string, mixed>  $distribution_links
     * @param  array<string, mixed>  $redactions
     * @param  array<string, mixed>  $slices
     */
    public function __construct(
        public readonly ?string $code,
        public readonly string $status,
        public readonly array $summary = [],
        public readonly array $overview = [],
        public readonly array $instructions = [],
        public readonly array $claims = [],
        public readonly array $settlement = [],
        public readonly array $treasury = [],
        public readonly array $evidence_summary = [],
        public readonly array $distribution_links = [],
        public readonly array $redactions = ['payloads' => 'not-loaded'],
        public readonly bool $authorized = false,
        public readonly array $slices = [],
    ) {}
}
