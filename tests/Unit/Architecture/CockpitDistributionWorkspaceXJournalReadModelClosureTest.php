<?php

declare(strict_types=1);

it('documents the distribution workspace x-journal read model closure', function () {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/540-distribution-workspace-x-journal-read-model-slice-3-closure.md');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Distribution Workspace can now display connected x-journal evidence summaries as read-only audit guidance.')
        ->toContain('It did not write journal entries')
        ->toContain('Raw payloads, provider payloads, wallet data, secrets, and mutable journal internals remain excluded.')
        ->and($cockpitCompass)->toContain('Distribution Workspace x-journal Read Model — Slice 3 Closure')
        ->and($cockpitCompass)->toContain('reports/540-distribution-workspace-x-journal-read-model-slice-3-closure.md')
        ->and($settlementCompass)->toContain('Distribution Workspace x-journal Read Model — Slice 3 Closure')
        ->and($settlementCompass)->toContain('Next recommended checkpoint: pause connected-service wiring and manually inspect the five primary Cockpit pages');
});
