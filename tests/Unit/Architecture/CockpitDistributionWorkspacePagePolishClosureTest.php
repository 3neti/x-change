<?php

declare(strict_types=1);

it('documents the distribution workspace page polish closure', function () {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/543-distribution-workspace-page-polish-slice-3-closure.md');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Distribution Workspace now has a more operator-facing primary scan path and less engineering-oriented panel copy.')
        ->toContain('It did not change route behavior')
        ->toContain('Connected context')
        ->and($cockpitCompass)->toContain('Distribution Workspace Page Polish — Slice 3 Closure')
        ->and($cockpitCompass)->toContain('reports/543-distribution-workspace-page-polish-slice-3-closure.md')
        ->and($settlementCompass)->toContain('Distribution Workspace Page Polish — Slice 3 Closure')
        ->and($settlementCompass)->toContain('Next recommended checkpoint: manually inspect the five primary Cockpit pages');
});
