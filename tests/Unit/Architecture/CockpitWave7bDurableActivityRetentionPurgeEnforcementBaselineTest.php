<?php

declare(strict_types=1);

it('documents cockpit mutation wave 7b durable activity retention purge enforcement baseline', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/122-wave-7b-durable-activity-retention-purge-enforcement-baseline.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Mutation Wave 7B — Durable Activity Retention / Purge Enforcement Baseline')
        ->and($report)->toContain('Status: Scaffolded / Baseline recorded')
        ->and($report)->toContain('Expired durable activity records must be purged or excluded before production default enablement.')
        ->and($report)->toContain('Durable activity recording remains disabled by default.')
        ->and($report)->toContain('No current Cockpit UI change is expected.')
        ->and($report)->toContain('7C — Recorder Failure Observability Implementation Baseline')
        ->and($cockpitCompass)->toContain('Cockpit Mutation Wave 7B — Durable Activity Retention / Purge Enforcement Baseline')
        ->and($cockpitCompass)->toContain('reports/122-wave-7b-durable-activity-retention-purge-enforcement-baseline.md')
        ->and($settlementCompass)->toContain('Cockpit Mutation Wave 7B — Durable Activity Retention / Purge Enforcement Baseline')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/122-wave-7b-durable-activity-retention-purge-enforcement-baseline.md');
});
