<?php

declare(strict_types=1);

it('documents campaign template quick generate runtime adoption decision closure', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/327-wave-52-campaign-template-quick-generate-runtime-adoption-decision-closure.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');
    $hostComponent = file_get_contents(dirname($packageRoot, 2).'/resources/js/cockpit/components/CockpitQuickGenerateSubmitPanel.vue');

    expect($report)
        ->toContain('Cockpit Wave 52')
        ->toContain('Campaign Template Quick Generate runtime adoption uses the existing x-change Quick Generate issuance handoff')
        ->toContain('cash.validation.mobile')
        ->toContain('inputs.fields[] = mobile')
        ->toContain('checked 58, ok 58, stale 0, missing 0, extra 0')
        ->toContain('Cockpit Wave 53 — Campaign Quick Generate Full URL / Distribution Link Readiness')
        ->and($cockpitCompass)
        ->toContain('Cockpit Wave 52 completed')
        ->toContain('Cockpit Wave 53 — Campaign Quick Generate Full URL / Distribution Link Readiness')
        ->and($settlementCompass)
        ->toContain('Cockpit Wave 52 complete')
        ->toContain('Cockpit Wave 53 — Campaign Quick Generate Full URL / Distribution Link Readiness')
        ->and($hostComponent)
        ->toContain('const validation = mobile ===')
        ->toContain('const fields = mobile ===');
});
