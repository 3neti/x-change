<?php

declare(strict_types=1);

it('documents distribution workspace final copy polish inspection copy slice', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/463-distribution-workspace-final-copy-polish-slice-1-inspection-copy.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Distribution Workspace Final Copy Polish — Slice 1 — Inspection Copy')
        ->and($report)->toContain('Distribution Workspace')
        ->and($report)->toContain('Distribution inspection')
        ->and($report)->toContain('Delivery channel status')
        ->and($report)->toContain('Print asset readiness')
        ->and($report)->toContain('Share asset readiness')
        ->and($report)->toContain('Distribution status summary')
        ->and($report)->toContain('No read-model behavior')
        ->and($cockpitCompass)->toContain('Distribution Workspace Final Copy Polish — Slice 1 — Inspection Copy')
        ->and($cockpitCompass)->toContain('reports/463-distribution-workspace-final-copy-polish-slice-1-inspection-copy.md')
        ->and($settlementCompass)->toContain('Distribution Workspace Final Copy Polish — Slice 1 — Inspection Copy')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/463-distribution-workspace-final-copy-polish-slice-1-inspection-copy.md');
});
