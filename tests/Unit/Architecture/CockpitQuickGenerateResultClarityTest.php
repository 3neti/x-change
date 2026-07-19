<?php

it('documents the quick generate result clarity slice', function (): void {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/549-quick-generate-result-clarity-slice-1.md');
    $closureReport = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/550-quick-generate-result-clarity-slice-2-closure.md');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Quick Generate Result Clarity Slice 1')
        ->toContain('Supporting result details')
        ->toContain('primary operator result surface')
        ->toContain('No routes, controllers, request payloads, issuance behavior, validation behavior, provider calls, wallet behavior, Treasury behavior, journal writes, action execution, feedback sends, campaign mutation, persistence changes, public API changes, or money movement were added')
        ->and($closureReport)->toContain('Quick Generate Result Clarity Slice 2 / Closure')
        ->and($closureReport)->toContain('authenticated Dusk browser smoke coverage')
        ->and($closureReport)->toContain('the success card as the primary operator surface')
        ->and($cockpitCompass)->toContain('Quick Generate Result Clarity')
        ->and($cockpitCompass)->toContain('reports/550-quick-generate-result-clarity-slice-2-closure.md')
        ->and($cockpitCompass)->toContain('reports/549-quick-generate-result-clarity-slice-1.md')
        ->and($settlementCompass)->toContain('Cockpit Quick Generate Result Clarity')
        ->and($settlementCompass)->toContain('Quick Generate result clarity closed');
});
