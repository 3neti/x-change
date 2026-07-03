<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Cockpit;

use Spatie\LaravelData\Data;

class CockpitQuickGenerateMutationPreconditionsReviewData extends Data
{
    /**
     * @param  array<int, CockpitQuickGenerateMutationPreconditionsReviewItemData>  $items
     * @param  array<string, mixed>  $redactions
     */
    public function __construct(
        public readonly string $status = 'not_wired',
        public readonly string $recommendation = 'not-loaded',
        public readonly array $items = [],
        public readonly array $redactions = ['payloads' => 'not-loaded'],
    ) {}
}
