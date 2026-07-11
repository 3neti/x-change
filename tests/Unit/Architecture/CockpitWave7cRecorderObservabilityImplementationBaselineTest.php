<?php

declare(strict_types=1);

it('documents cockpit mutation wave 7c recorder observability implementation baseline', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/123-wave-7c-recorder-observability-implementation-baseline.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Mutation Wave 7C — Recorder Failure Observability Implementation Baseline')
        ->and($report)->toContain('Status: Scaffolded / Baseline recorded')
        ->and($report)->toContain('Recorder and handoff failures must be visible as safe diagnostics without blocking issuance.')
        ->and($report)->toContain('Durable activity recording remains disabled by default.')
        ->and($report)->toContain('No current Cockpit UI change is expected.')
        ->and($report)->toContain('7D — Journal Handoff Hardening Baseline')
        ->and($cockpitCompass)->toContain('Cockpit Mutation Wave 7C — Recorder Failure Observability Implementation Baseline')
        ->and($cockpitCompass)->toContain('reports/123-wave-7c-recorder-observability-implementation-baseline.md')
        ->and($settlementCompass)->toContain('Cockpit Mutation Wave 7C — Recorder Failure Observability Implementation Baseline')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/123-wave-7c-recorder-observability-implementation-baseline.md');
});
