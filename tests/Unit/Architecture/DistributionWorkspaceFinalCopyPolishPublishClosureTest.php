<?php

declare(strict_types=1);

it('documents distribution workspace final copy polish host publish closure', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/464-distribution-workspace-final-copy-polish-slice-2-publish-closure.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Distribution Workspace Final Copy Polish — Slice 2 — Host Publish / Closure')
        ->and($report)->toContain('checked 60, ok 60, stale 0, missing 0, extra 0')
        ->and($report)->toContain('Distribution Workspace')
        ->and($report)->toContain('Distribution inspection')
        ->and($report)->toContain('Read-only claim link')
        ->and($report)->toContain('npm run build')
        ->and($report)->toContain('No read-model behavior')
        ->and($cockpitCompass)->toContain('Distribution Workspace Final Copy Polish — Slice 2 — Host Publish / Closure')
        ->and($cockpitCompass)->toContain('reports/464-distribution-workspace-final-copy-polish-slice-2-publish-closure.md')
        ->and($settlementCompass)->toContain('Distribution Workspace Final Copy Polish — Slice 2 — Host Publish / Closure')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/464-distribution-workspace-final-copy-polish-slice-2-publish-closure.md');
});
