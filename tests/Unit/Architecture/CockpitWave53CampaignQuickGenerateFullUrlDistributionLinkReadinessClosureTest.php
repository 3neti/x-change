<?php

declare(strict_types=1);

it('documents campaign quick generate full url distribution link readiness closure', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/331-wave-53-campaign-quick-generate-full-url-distribution-link-readiness-closure.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');
    $hostComponent = file_get_contents(dirname($packageRoot, 2).'/resources/js/cockpit/components/CockpitQuickGenerateSubmitPanel.vue');

    expect($report)
        ->toContain('Cockpit Wave 53')
        ->toContain('Beneficiary Pay Code URL')
        ->toContain('result.links.redeem')
        ->toContain('result.links.redeem_path')
        ->toContain('checked 58, ok 58, stale 0, missing 0, extra 0')
        ->toContain('Cockpit Wave 54 — Pay Code Detail / Distribution Full URL Continuity')
        ->and($cockpitCompass)
        ->toContain('Cockpit Wave 53 completed')
        ->toContain('Cockpit Wave 54 — Pay Code Detail / Distribution Full URL Continuity')
        ->and($settlementCompass)
        ->toContain('Cockpit Wave 53 complete')
        ->toContain('Cockpit Wave 54 — Pay Code Detail / Distribution Full URL Continuity')
        ->and($hostComponent)
        ->toContain('Beneficiary Pay Code URL')
        ->toContain('cockpit-quick-generate-beneficiary-url-panel')
        ->toContain('beneficiaryRedeemUrl');
});
