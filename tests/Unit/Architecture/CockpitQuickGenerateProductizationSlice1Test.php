<?php

declare(strict_types=1);

it('documents quick generate productization slice one', function (): void {
    $packageRoot = dirname(__DIR__, 3);

    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/400-quick-generate-productization-slice-1-result-panel-diagnostic-demotion-plan.md');
    $quickGenerateSubmitPanel = file_get_contents($packageRoot.'/resources/js/cockpit/components/CockpitQuickGenerateSubmitPanel.vue');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Quick Generate Productization Slice 1')
        ->toContain('Generation complete')
        ->toContain('beneficiary URL readiness')
        ->toContain('pricing preflight status')
        ->toContain('funding preflight status')
        ->toContain('activity runtime status')
        ->toContain('No new behavior was added');

    expect($quickGenerateSubmitPanel)
        ->toContain('cockpit-quick-generate-productized-result-card')
        ->toContain('generationSummary')
        ->toContain('Generated through the existing x-change issuance')
        ->toContain('handoff. Cockpit presents the result')
        ->toContain('copy the beneficiary URL')
        ->toContain('approved external')
        ->toContain('distribution workflow.');

    expect($cockpitCompass)
        ->toContain('Quick Generate Productization Slice 1');

    expect($settlementCompass)
        ->toContain('Quick Generate Productization Slice 1');
});
