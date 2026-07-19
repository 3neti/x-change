<?php

declare(strict_types=1);

it('documents distribution workspace readiness consolidation slice 1', function (): void {
    $packageRoot = dirname(__DIR__, 3);

    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/573-distribution-workspace-readiness-consolidation-slice-1.md');
    $page = file_get_contents($packageRoot.'/resources/js/cockpit/pages/DistributionWorkspace.vue');
    $frontendTest = file_get_contents($packageRoot.'/tests/frontend/cockpit/CockpitDistributionWorkspaceFoundation.test.ts');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Distribution Workspace Readiness Consolidation — Slice 1')
        ->toContain('Detailed readiness panels')
        ->toContain('presentation-only consolidation')
        ->and($page)->toContain('cockpit-distribution-readiness-panel-guide')
        ->and($page)->toContain('Detailed readiness panels')
        ->and($page)->toContain('details below')
        ->and($page)->not->toContain('cockpit-distribution-channel-artifact-readiness-item')
        ->and($frontendTest)->toContain('cockpit-distribution-readiness-panel-guide')
        ->and($frontendTest)->toContain('Detailed readiness panels')
        ->and($frontendTest)->toContain('toHaveLength(0)')
        ->and($cockpitCompass)->toContain('Distribution Workspace Readiness Consolidation Slice 1')
        ->and($cockpitCompass)->toContain('reports/573-distribution-workspace-readiness-consolidation-slice-1.md')
        ->and($settlementCompass)->toContain('Distribution Workspace Readiness Consolidation — Slice 1')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/573-distribution-workspace-readiness-consolidation-slice-1.md');
});
