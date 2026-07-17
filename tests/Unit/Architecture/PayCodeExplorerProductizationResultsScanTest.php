<?php

declare(strict_types=1);

it('documents pay code explorer productization results scan slice', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/459-pay-code-explorer-productization-slice-1-results-scan.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Pay Code Explorer Productization — Slice 1 — Results Scan')
        ->and($report)->toContain('Pay Code results')
        ->and($report)->toContain('Identify')
        ->and($report)->toContain('Assess')
        ->and($report)->toContain('Navigate')
        ->and($report)->toContain('Navigation-only')
        ->and($report)->toContain('No filter behavior')
        ->and($cockpitCompass)->toContain('Pay Code Explorer Productization — Slice 1 — Results Scan')
        ->and($cockpitCompass)->toContain('reports/459-pay-code-explorer-productization-slice-1-results-scan.md')
        ->and($settlementCompass)->toContain('Pay Code Explorer Productization — Slice 1 — Results Scan')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/459-pay-code-explorer-productization-slice-1-results-scan.md');
});
