<?php

it('documents distribution workspace secondary panel cleanup slice 1', function (): void {
    $packageRoot = dirname(__DIR__, 3);

    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/563-distribution-workspace-secondary-panel-cleanup-slice-1.md');
    $digitalPanel = file_get_contents($packageRoot.'/resources/js/cockpit/components/CockpitDigitalDistributionPanel.vue');
    $printPanel = file_get_contents($packageRoot.'/resources/js/cockpit/components/CockpitPrintTemplatePanel.vue');
    $sharePanel = file_get_contents($packageRoot.'/resources/js/cockpit/components/CockpitShareQrPanel.vue');
    $analyticsPanel = file_get_contents($packageRoot.'/resources/js/cockpit/components/CockpitDistributionAnalyticsPanel.vue');
    $frontendTest = file_get_contents($packageRoot.'/tests/frontend/cockpit/CockpitDistributionWorkspaceFoundation.test.ts');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Distribution Workspace Secondary Panel Cleanup — Slice 1')
        ->toContain('presentation-only')
        ->toContain('did not change routes, controllers, queries, read-model hydration')
        ->and($digitalPanel)->toContain('<details')
        ->and($digitalPanel)->toContain('Channel and follow-up facts are read-only')
        ->and($printPanel)->toContain('<details')
        ->and($printPanel)->toContain('Template previews are read-only')
        ->and($sharePanel)->toContain('<details')
        ->and($sharePanel)->toContain('Link, QR, and short-link readiness')
        ->and($analyticsPanel)->toContain('<details')
        ->and($analyticsPanel)->toContain('Operational evidence')
        ->and($frontendTest)->toContain('cockpit-digital-distribution-panel')
        ->and($frontendTest)->toContain('cockpit-distribution-analytics-panel')
        ->and($cockpitCompass)->toContain('Distribution Workspace Secondary Panel Cleanup Slice 1')
        ->and($cockpitCompass)->toContain('reports/563-distribution-workspace-secondary-panel-cleanup-slice-1.md')
        ->and($settlementCompass)->toContain('Distribution Workspace Secondary Panel Cleanup Slice 1')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/563-distribution-workspace-secondary-panel-cleanup-slice-1.md');
});
