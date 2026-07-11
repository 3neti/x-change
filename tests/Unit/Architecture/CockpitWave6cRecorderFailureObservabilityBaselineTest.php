<?php

declare(strict_types=1);

it('documents cockpit mutation wave 6c recorder failure observability baseline', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/113-wave-6c-recorder-failure-observability-baseline.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Mutation Wave 6C — Recorder Failure Observability Baseline')
        ->and($report)->toContain('Status: Scaffolded / Decision recorded')
        ->and($report)->toContain('Define how durable activity recorder failures must be observed without blocking issuance.')
        ->and($report)->toContain('Durable activity recording remains disabled by default.')
        ->and($report)->toContain('6D — Journal Handoff Default Policy Decision')
        ->and($cockpitCompass)->toContain('Cockpit Mutation Wave 6C — Recorder Failure Observability Baseline')
        ->and($cockpitCompass)->toContain('reports/113-wave-6c-recorder-failure-observability-baseline.md')
        ->and($settlementCompass)->toContain('Cockpit Mutation Wave 6C — Recorder Failure Observability Baseline')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/113-wave-6c-recorder-failure-observability-baseline.md');
});
