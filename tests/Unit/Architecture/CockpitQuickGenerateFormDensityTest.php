<?php

it('documents the quick generate form density slice', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/551-quick-generate-form-density-slice-1.md');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Quick Generate Form Density Slice 1')
        ->toContain('Contract Builder Checklist')
        ->toContain('VoucherInstruction DTO coverage')
        ->toContain('collapsed disclosure')
        ->toContain('No routes, controllers, request payloads, issuance behavior, validation behavior, provider calls, wallet behavior, Treasury behavior, journal writes, action execution, feedback sends, campaign mutation, persistence changes, public API changes, or money movement were added')
        ->and($cockpitCompass)->toContain('Quick Generate Form Density')
        ->and($cockpitCompass)->toContain('reports/551-quick-generate-form-density-slice-1.md')
        ->and($settlementCompass)->toContain('Cockpit Quick Generate Form Density')
        ->and($settlementCompass)->toContain('Quick Generate form density in progress');
});
