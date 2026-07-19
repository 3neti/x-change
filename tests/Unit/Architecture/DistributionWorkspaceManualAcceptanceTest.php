<?php

it('documents distribution workspace manual acceptance checklist slice 1', function (): void {
    $packageRoot = dirname(__DIR__, 3);

    $checklist = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/565-distribution-workspace-manual-acceptance-slice-1-checklist.md');
    $distributionPage = file_get_contents($packageRoot.'/resources/js/cockpit/pages/DistributionWorkspace.vue');
    $foundationTest = file_get_contents($packageRoot.'/tests/frontend/cockpit/CockpitDistributionWorkspaceFoundation.test.ts');
    $browserTest = file_get_contents($packageRoot.'/../../tests/Browser/CockpitVoucherDetailDistributionSmokeTest.php');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($checklist)
        ->toContain('Distribution Workspace Manual Acceptance — Slice 1 Checklist')
        ->toContain('pending-human-visual-acceptance')
        ->toContain('/x/cockpit/pay-codes/{code}/distribution')
        ->toContain('Do not record `Pass` until explicit human evidence is supplied.')
        ->toContain('does not change routes, controllers, queries, read-model hydration')
        ->and($distributionPage)->toContain('cockpit-distribution-workspace-shell')
        ->and($distributionPage)->toContain('cockpit-distribution-manual-checklist')
        ->and($foundationTest)->toContain('cockpit-distribution-manual-checklist')
        ->and($browserTest)->toContain('/x/cockpit/pay-codes/')
        ->and($browserTest)->toContain('/distribution')
        ->and($cockpitCompass)->toContain('Distribution Workspace Manual Acceptance Slice 1')
        ->and($cockpitCompass)->toContain('reports/565-distribution-workspace-manual-acceptance-slice-1-checklist.md')
        ->and($settlementCompass)->toContain('Distribution Workspace Manual Acceptance Slice 1')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/565-distribution-workspace-manual-acceptance-slice-1-checklist.md');
});
