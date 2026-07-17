<?php

declare(strict_types=1);

it('documents quick generate final copy polish host publish closure', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/466-quick-generate-final-copy-polish-slice-2-publish-closure.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Quick Generate Final Copy Polish — Slice 2 — Host Publish / Closure')
        ->and($report)->toContain('checked 60, ok 60, stale 0, missing 0, extra 0')
        ->and($report)->toContain('Pay Code generation')
        ->and($report)->toContain('Quick Generate')
        ->and($report)->toContain('Operator input reference')
        ->and($report)->toContain('Preflight summary')
        ->and($report)->toContain('No route behavior')
        ->and($cockpitCompass)->toContain('Quick Generate Final Copy Polish — Slice 2 — Host Publish / Closure')
        ->and($cockpitCompass)->toContain('reports/466-quick-generate-final-copy-polish-slice-2-publish-closure.md')
        ->and($settlementCompass)->toContain('Quick Generate Final Copy Polish — Slice 2 — Host Publish / Closure')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/466-quick-generate-final-copy-polish-slice-2-publish-closure.md');
});
