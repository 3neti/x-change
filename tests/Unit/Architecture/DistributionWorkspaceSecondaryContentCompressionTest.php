<?php

declare(strict_types=1);

it('documents distribution workspace secondary content compression slice 1', function (): void {
    $packageRoot = dirname(__DIR__, 3);

    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/622-distribution-workspace-secondary-content-compression-slice-1.md');
    $page = file_get_contents($packageRoot.'/resources/js/cockpit/pages/DistributionWorkspace.vue');
    $frontendTest = file_get_contents($packageRoot.'/tests/frontend/cockpit/CockpitDistributionWorkspaceFoundation.test.ts');
    $compass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Distribution Workspace Secondary Content Compression — Slice 1')
        ->toContain('operator-facing read-only limits')
        ->toContain('Presentation-only boundary-language compression')
        ->and($page)->toContain('data-testid="cockpit-distribution-workspace-boundary"')
        ->and($page)->toContain('Read-only limits')
        ->and($page)->toContain('Inspection only. This page can display and copy the claim URL')
        ->and($frontendTest)->toContain("not.toContain('mutate vouchers')")
        ->and($compass)->toContain('Distribution Workspace Secondary Content Compression — Slice 1')
        ->and($settlementCompass)->toContain('Distribution Workspace Secondary Content Compression — Slice 1');
});

it('documents distribution workspace secondary content compression slice 2', function (): void {
    $packageRoot = dirname(__DIR__, 3);

    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/623-distribution-workspace-secondary-content-compression-slice-2.md');
    $page = file_get_contents($packageRoot.'/resources/js/cockpit/pages/DistributionWorkspace.vue');
    $frontendTest = file_get_contents($packageRoot.'/tests/frontend/cockpit/CockpitDistributionWorkspaceFoundation.test.ts');
    $compass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Distribution Workspace Secondary Content Compression — Slice 2')
        ->toContain('collapsed secondary disclosure')
        ->toContain('Presentation-only connected-context compression')
        ->and($page)->toContain('data-testid="cockpit-distribution-connected-context-summary"')
        ->and($page)->toContain('4 read-only facts')
        ->and($frontendTest)->toContain("expect(connectedContext.element.tagName.toLowerCase()).toBe('details')")
        ->and($frontendTest)->toContain("expect(connectedContext.attributes('open')).toBeUndefined()")
        ->and($compass)->toContain('Distribution Workspace Secondary Content Compression — Slice 2')
        ->and($settlementCompass)->toContain('Distribution Workspace Secondary Content Compression — Slice 2');
});
