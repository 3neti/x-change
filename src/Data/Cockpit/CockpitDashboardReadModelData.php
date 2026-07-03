<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Data\Cockpit;

use Spatie\LaravelData\Data;

class CockpitDashboardReadModelData extends Data
{
    /**
     * @param  array<int, CockpitDashboardMetricData>  $metrics
     * @param  array<int, CockpitDashboardPipelineStageData>  $pipeline
     * @param  array<int, CockpitDashboardRiskSignalData>  $risk_signals
     * @param  array<int, CockpitDashboardActivityData>  $activity
     * @param  array<string, mixed>  $redactions
     */
    public function __construct(
        public readonly string $status,
        public readonly bool $authorized = false,
        public readonly array $metrics = [],
        public readonly array $pipeline = [],
        public readonly array $risk_signals = [],
        public readonly array $activity = [],
        public readonly array $redactions = ['payloads' => 'not-loaded'],
    ) {}
}
