<?php

declare(strict_types=1);

it('documents distribution workspace secondary panel copy polish slice 1', function (): void {
    $packageRoot = dirname(__DIR__, 3);

    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/568-distribution-workspace-secondary-panel-copy-polish-slice-1.md');
    $digitalPanel = file_get_contents($packageRoot.'/resources/js/cockpit/components/CockpitDigitalDistributionPanel.vue');
    $printPanel = file_get_contents($packageRoot.'/resources/js/cockpit/components/CockpitPrintTemplatePanel.vue');
    $sharePanel = file_get_contents($packageRoot.'/resources/js/cockpit/components/CockpitShareQrPanel.vue');
    $analyticsPanel = file_get_contents($packageRoot.'/resources/js/cockpit/components/CockpitDistributionAnalyticsPanel.vue');
    $hostDigitalPanel = file_get_contents($packageRoot.'/../../resources/js/cockpit/components/CockpitDigitalDistributionPanel.vue');
    $hostPrintPanel = file_get_contents($packageRoot.'/../../resources/js/cockpit/components/CockpitPrintTemplatePanel.vue');
    $hostSharePanel = file_get_contents($packageRoot.'/../../resources/js/cockpit/components/CockpitShareQrPanel.vue');
    $hostAnalyticsPanel = file_get_contents($packageRoot.'/../../resources/js/cockpit/components/CockpitDistributionAnalyticsPanel.vue');
    $frontendTest = file_get_contents($packageRoot.'/tests/frontend/cockpit/CockpitDistributionWorkspaceFoundation.test.ts');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');
    $closure = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/569-distribution-workspace-secondary-panel-copy-polish-slice-2-closure.md');

    expect($report)
        ->toContain('Distribution Workspace Secondary Panel Copy Polish — Slice 1')
        ->toContain('presentation-only copy polish')
        ->and($digitalPanel)->toContain('Notification channels')
        ->and($digitalPanel)->toContain('Message and follow-up readiness')
        ->and($digitalPanel)->toContain('Cockpit does not send notifications')
        ->and($digitalPanel)->toContain('Why disabled')
        ->and($hostDigitalPanel)->toContain('Notification channels')
        ->and($hostDigitalPanel)->toContain('Why disabled')
        ->and($printPanel)->toContain('Printable handout options')
        ->and($printPanel)->toContain('future handout ideas only')
        ->and($hostPrintPanel)->toContain('Printable handout options')
        ->and($sharePanel)->toContain('Share options')
        ->and($sharePanel)->toContain('Only the claim URL can be copied today')
        ->and($sharePanel)->toContain('What this means')
        ->and($hostSharePanel)->toContain('Share options')
        ->and($hostSharePanel)->toContain('What this means')
        ->and($analyticsPanel)->toContain('Status evidence')
        ->and($analyticsPanel)->toContain('Delivery and campaign signals')
        ->and($analyticsPanel)->toContain('Why this status appears')
        ->and($hostAnalyticsPanel)->toContain('Status evidence')
        ->and($hostAnalyticsPanel)->toContain('Why this status appears')
        ->and($frontendTest)->toContain('Notification channels')
        ->and($frontendTest)->toContain('Share options')
        ->and($frontendTest)->toContain('Status evidence')
        ->and($closure)->toContain('Distribution Workspace Secondary Panel Copy Polish — Slice 2 / Closure')
        ->and($closure)->toContain('php artisan x-change:doctor --assets --no-interaction')
        ->and($closure)->toContain('php artisan dusk tests/Browser/CockpitVoucherDetailDistributionSmokeTest.php')
        ->and($cockpitCompass)->toContain('Distribution Workspace Secondary Panel Copy Polish Slice 1')
        ->and($cockpitCompass)->toContain('reports/568-distribution-workspace-secondary-panel-copy-polish-slice-1.md')
        ->and($cockpitCompass)->toContain('Distribution Workspace Secondary Panel Copy Polish Slice 2 / Closure')
        ->and($cockpitCompass)->toContain('reports/569-distribution-workspace-secondary-panel-copy-polish-slice-2-closure.md')
        ->and($settlementCompass)->toContain('Distribution Workspace Secondary Panel Copy Polish — Slice 1')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/568-distribution-workspace-secondary-panel-copy-polish-slice-1.md')
        ->and($settlementCompass)->toContain('Distribution Workspace Secondary Panel Copy Polish — Slice 2 / Closure')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/569-distribution-workspace-secondary-panel-copy-polish-slice-2-closure.md');
});
