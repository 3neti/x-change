<?php

it('documents the quick generate result clarity slice', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/549-quick-generate-result-clarity-slice-1.md');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Quick Generate Result Clarity Slice 1')
        ->toContain('Supporting result details')
        ->toContain('primary operator result surface')
        ->toContain('No routes, controllers, request payloads, issuance behavior, validation behavior, provider calls, wallet behavior, Treasury behavior, journal writes, action execution, feedback sends, campaign mutation, persistence changes, public API changes, or money movement were added')
        ->and($cockpitCompass)->toContain('Quick Generate Result Clarity')
        ->and($cockpitCompass)->toContain('reports/549-quick-generate-result-clarity-slice-1.md')
        ->and($settlementCompass)->toContain('Cockpit Quick Generate Result Clarity')
        ->and($settlementCompass)->toContain('Quick Generate result clarity in progress');
});
