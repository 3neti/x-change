<?php

declare(strict_types=1);

it('documents quick generate final copy polish generation copy slice', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/465-quick-generate-final-copy-polish-slice-1-generation-copy.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Quick Generate Final Copy Polish — Slice 1 — Generation Copy')
        ->and($report)->toContain('Pay Code generation')
        ->and($report)->toContain('Quick Generate')
        ->and($report)->toContain('Operator input reference')
        ->and($report)->toContain('Preflight summary')
        ->and($report)->toContain('No route behavior')
        ->and($cockpitCompass)->toContain('Quick Generate Final Copy Polish — Slice 1 — Generation Copy')
        ->and($cockpitCompass)->toContain('reports/465-quick-generate-final-copy-polish-slice-1-generation-copy.md')
        ->and($settlementCompass)->toContain('Quick Generate Final Copy Polish — Slice 1 — Generation Copy')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/465-quick-generate-final-copy-polish-slice-1-generation-copy.md');
});
