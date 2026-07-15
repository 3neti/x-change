<?php

declare(strict_types=1);

it('documents quick generate productization slice four', function (): void {
    $packageRoot = dirname(__DIR__, 3);

    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/403-quick-generate-productization-slice-4-financial-readiness.md');
    $submitPanel = file_get_contents($packageRoot.'/resources/js/cockpit/components/CockpitQuickGenerateSubmitPanel.vue');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Quick Generate Productization Slice 4')
        ->toContain('Pricing summary')
        ->toContain('Funding summary')
        ->toContain('already-returned operator-safe preflight data');

    expect($submitPanel)
        ->toContain('cockpit-quick-generate-primary-financial-readiness')
        ->toContain('Pricing summary')
        ->toContain('Funding summary')
        ->toContain('Base fee:')
        ->toContain('Authority:');

    expect($cockpitCompass)->toContain('Quick Generate Productization Slice 4');
    expect($settlementCompass)->toContain('Quick Generate Productization Slice 4');
});
