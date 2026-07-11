<?php

declare(strict_types=1);

it('documents cockpit mutation wave 7h activity search filter implementation baseline', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/128-wave-7h-activity-search-filter-implementation-baseline.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Mutation Wave 7H — Activity Search / Filter Implementation Baseline')
        ->and($report)->toContain('Status: Scaffolded / Baseline recorded')
        ->and($report)->toContain('Activity search must remain read-only, bounded, authorized, tenant-scoped, and redaction-safe.')
        ->and($report)->toContain('Durable activity recording remains disabled by default.')
        ->and($report)->toContain('No current Cockpit UI change is expected.')
        ->and($report)->toContain('7I — Projection / Queue Seam Implementation Baseline')
        ->and($cockpitCompass)->toContain('Cockpit Mutation Wave 7H — Activity Search / Filter Implementation Baseline')
        ->and($cockpitCompass)->toContain('reports/128-wave-7h-activity-search-filter-implementation-baseline.md')
        ->and($settlementCompass)->toContain('Cockpit Mutation Wave 7H — Activity Search / Filter Implementation Baseline')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/128-wave-7h-activity-search-filter-implementation-baseline.md');
});
