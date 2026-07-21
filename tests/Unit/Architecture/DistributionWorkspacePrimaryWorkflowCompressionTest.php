<?php

declare(strict_types=1);

it('documents distribution workspace primary workflow compression slice 1', function (): void {
    $packageRoot = dirname(__DIR__, 3);

    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/619-distribution-workspace-primary-workflow-compression-slice-1.md');
    $page = file_get_contents($packageRoot.'/resources/js/cockpit/pages/DistributionWorkspace.vue');
    $frontendTest = file_get_contents($packageRoot.'/tests/frontend/cockpit/CockpitDistributionWorkspaceFoundation.test.ts');

    expect($report)
        ->toContain('Distribution Workspace Primary Workflow Compression — Slice 1')
        ->toContain('compact operational header')
        ->toContain('Presentation-only shell compression')
        ->and($page)->toContain('data-testid="cockpit-distribution-workspace-header"')
        ->and($page)->toContain('data-testid="cockpit-distribution-workspace-header-facts"')
        ->and($page)->toContain('data-testid="cockpit-distribution-workspace-boundary"')
        ->and($page)->toContain('Workspace rules')
        ->and($frontendTest)->toContain('renders the workspace shell as a compact operational header');
});

it('documents distribution workspace primary workflow compression slice 2', function (): void {
    $packageRoot = dirname(__DIR__, 3);

    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/620-distribution-workspace-primary-workflow-compression-slice-2.md');
    $page = file_get_contents($packageRoot.'/resources/js/cockpit/pages/DistributionWorkspace.vue');
    $frontendTest = file_get_contents($packageRoot.'/tests/frontend/cockpit/CockpitDistributionWorkspaceFoundation.test.ts');

    expect($report)
        ->toContain('Distribution Workspace Primary Workflow Compression — Slice 2')
        ->toContain('compact readiness strip')
        ->toContain('Presentation-only primary workflow compression')
        ->and($page)->toContain('data-testid="cockpit-distribution-primary-readiness-strip"')
        ->and($page)->toContain('Manual distribution checklist')
        ->and($page)->toContain('5 steps')
        ->and($frontendTest)->toContain('compresses primary readiness around the manual next step');
});
