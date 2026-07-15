<?php

declare(strict_types=1);

it('documents quick generate productization slice three', function (): void {
    $packageRoot = dirname(__DIR__, 3);

    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/402-quick-generate-productization-slice-3-beneficiary-url-actions.md');
    $submitPanel = file_get_contents($packageRoot.'/resources/js/cockpit/components/CockpitQuickGenerateSubmitPanel.vue');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Quick Generate Productization Slice 3')
        ->toContain('Primary next step')
        ->toContain('Open claim URL')
        ->toContain('Inspect Pay Code')
        ->toContain('read-only navigation/copy aids');

    expect($submitPanel)
        ->toContain('cockpit-quick-generate-primary-next-actions')
        ->toContain('cockpit-quick-generate-primary-claim-link')
        ->toContain('cockpit-quick-generate-primary-detail-link')
        ->toContain('Copy or inspect the beneficiary claim URL');

    expect($cockpitCompass)->toContain('Quick Generate Productization Slice 3');
    expect($settlementCompass)->toContain('Quick Generate Productization Slice 3');
});
