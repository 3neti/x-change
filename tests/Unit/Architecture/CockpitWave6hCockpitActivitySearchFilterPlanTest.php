<?php

declare(strict_types=1);

it('documents cockpit mutation wave 6h cockpit activity search   filter plan', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/118-wave-6h-cockpit-activity-search-filter-plan.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Mutation Wave 6H — Cockpit Activity Search / Filter Plan')
        ->and($report)->toContain('Status: Scaffolded / Decision recorded')
        ->and($report)->toContain('Plan future read-only search and filtering for durable operator activity history.')
        ->and($report)->toContain('Durable activity recording remains disabled by default.')
        ->and($report)->toContain('6I — High-Volume Projection / Queue Decision')
        ->and($cockpitCompass)->toContain('Cockpit Mutation Wave 6H — Cockpit Activity Search / Filter Plan')
        ->and($cockpitCompass)->toContain('reports/118-wave-6h-cockpit-activity-search-filter-plan.md')
        ->and($settlementCompass)->toContain('Cockpit Mutation Wave 6H — Cockpit Activity Search / Filter Plan')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/118-wave-6h-cockpit-activity-search-filter-plan.md');
});
