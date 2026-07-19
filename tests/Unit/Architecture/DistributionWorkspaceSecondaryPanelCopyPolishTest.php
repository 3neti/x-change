<?php

declare(strict_types=1);

it('documents distribution workspace secondary panel copy polish slice 1', function (): void {
    $packageRoot = dirname(__DIR__, 3);

    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/568-distribution-workspace-secondary-panel-copy-polish-slice-1.md');
    $digitalPanel = file_get_contents($packageRoot.'/resources/js/cockpit/components/CockpitDigitalDistributionPanel.vue');
    $printPanel = file_get_contents($packageRoot.'/resources/js/cockpit/components/CockpitPrintTemplatePanel.vue');
    $sharePanel = file_get_contents($packageRoot.'/resources/js/cockpit/components/CockpitShareQrPanel.vue');
    $analyticsPanel = file_get_contents($packageRoot.'/resources/js/cockpit/components/CockpitDistributionAnalyticsPanel.vue');
    $frontendTest = file_get_contents($packageRoot.'/tests/frontend/cockpit/CockpitDistributionWorkspaceFoundation.test.ts');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Distribution Workspace Secondary Panel Copy Polish — Slice 1')
        ->toContain('presentation-only copy polish')
        ->and($digitalPanel)->toContain('Notification channels')
        ->and($digitalPanel)->toContain('Message and follow-up readiness')
        ->and($digitalPanel)->toContain('Cockpit does not send notifications')
        ->and($digitalPanel)->toContain('Why disabled')
        ->and($printPanel)->toContain('Printable handout options')
        ->and($printPanel)->toContain('future handout ideas only')
        ->and($sharePanel)->toContain('Share options')
        ->and($sharePanel)->toContain('Only the claim URL can be copied today')
        ->and($sharePanel)->toContain('What this means')
        ->and($analyticsPanel)->toContain('Status evidence')
        ->and($analyticsPanel)->toContain('Delivery and campaign signals')
        ->and($analyticsPanel)->toContain('Why this status appears')
        ->and($frontendTest)->toContain('Notification channels')
        ->and($frontendTest)->toContain('Share options')
        ->and($frontendTest)->toContain('Status evidence')
        ->and($cockpitCompass)->toContain('Distribution Workspace Secondary Panel Copy Polish Slice 1')
        ->and($cockpitCompass)->toContain('reports/568-distribution-workspace-secondary-panel-copy-polish-slice-1.md')
        ->and($settlementCompass)->toContain('Distribution Workspace Secondary Panel Copy Polish — Slice 1')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/568-distribution-workspace-secondary-panel-copy-polish-slice-1.md');
});
