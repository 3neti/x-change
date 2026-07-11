<?php

declare(strict_types=1);

it('documents cockpit mutation wave 8f recorder failure telemetry runtime plan', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/136-wave-8f-recorder-failure-telemetry-runtime-plan.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Mutation Wave 8F — Recorder Failure Telemetry Runtime Plan')
        ->and($report)->toContain('Status: Scaffolded / Runtime planning recorded')
        ->and($report)->toContain('Recorder and handoff failures should emit safe diagnostics and logs before production default enablement.')
        ->and($report)->toContain('Durable activity production default remains disabled.')
        ->and($report)->toContain('No current Cockpit UI change is expected.')
        ->and($report)->toContain('8G — Handoff Runtime Enablement Gate Plan')
        ->and($cockpitCompass)->toContain('Cockpit Mutation Wave 8F — Recorder Failure Telemetry Runtime Plan')
        ->and($cockpitCompass)->toContain('reports/136-wave-8f-recorder-failure-telemetry-runtime-plan.md')
        ->and($settlementCompass)->toContain('Cockpit Mutation Wave 8F — Recorder Failure Telemetry Runtime Plan')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/136-wave-8f-recorder-failure-telemetry-runtime-plan.md');
});
