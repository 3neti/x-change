<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Cockpit;

use Spatie\LaravelData\Data;

class CockpitQuickGenerateMutationHandoffPlanData extends Data
{
    /**
     * @param  array<int, CockpitQuickGenerateMutationHandoffPlanStepData>  $steps
     * @param  array<string, mixed>  $redactions
     */
    public function __construct(
        public readonly string $status = 'not_wired',
        public readonly array $steps = [],
        public readonly array $redactions = ['payloads' => 'not-loaded'],
    ) {}
}
