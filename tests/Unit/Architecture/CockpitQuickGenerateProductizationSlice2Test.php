<?php

declare(strict_types=1);

it('documents quick generate productization slice two', function (): void {
    $packageRoot = dirname(__DIR__, 3);

    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/401-quick-generate-productization-slice-2-diagnostic-history-demotion.md');
    $handoffPanel = file_get_contents($packageRoot.'/resources/js/cockpit/components/CockpitGenerateActionPanel.vue');
    $diagnosticsDisclosure = file_get_contents($packageRoot.'/resources/js/cockpit/components/CockpitDiagnosticsDisclosure.vue');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Quick Generate Productization Slice 2')
        ->toContain('Issuance handoff status')
        ->toContain('Engineering history')
        ->toContain('No backend behavior changed');

    expect($handoffPanel)
        ->toContain('Issuance handoff status')
        ->toContain('The form above is the only operator submit control')
        ->not->toContain('data-testid="cockpit-generate-button"');

    expect($diagnosticsDisclosure)
        ->toContain('Engineering history')
        ->toContain('Show diagnostic history');

    expect($cockpitCompass)->toContain('Quick Generate Productization Slice 2');
    expect($settlementCompass)->toContain('Quick Generate Productization Slice 2');
});
