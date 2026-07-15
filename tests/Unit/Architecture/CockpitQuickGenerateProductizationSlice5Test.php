<?php

declare(strict_types=1);

it('documents quick generate productization slice five', function (): void {
    $packageRoot = dirname(__DIR__, 3);

    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/404-quick-generate-productization-slice-5-activity-handoff-status.md');
    $submitPanel = file_get_contents($packageRoot.'/resources/js/cockpit/components/CockpitQuickGenerateSubmitPanel.vue');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Quick Generate Productization Slice 5')
        ->toContain('Downstream handoff status')
        ->toContain('journal handoff status')
        ->toContain('action handoff status')
        ->toContain('feedback handoff status')
        ->toContain('display-only');

    expect($submitPanel)
        ->toContain('cockpit-quick-generate-primary-handoff-status')
        ->toContain('downstreamHandoffSummary')
        ->toContain('Journal')
        ->toContain('Action')
        ->toContain('Feedback');

    expect($cockpitCompass)->toContain('Quick Generate Productization Slice 5');
    expect($settlementCompass)->toContain('Quick Generate Productization Slice 5');
});
