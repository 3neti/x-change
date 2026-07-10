<?php

declare(strict_types=1);

it('documents the cockpit durable activity closure and production readiness decision', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/076-durable-activity-closure-production-readiness-decision.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Mutation Wave 3N — Durable Activity Closure / Production Readiness Decision')
        ->and($report)->toContain('Status: Implemented')
        ->and($report)->toContain('The durable operator issuance activity mini-wave is closed as an opt-in baseline')
        ->and($report)->toContain('Ready for local/manual opt-in testing')
        ->and($report)->toContain('Not yet ready to enable by default in production')
        ->and($report)->toContain('Cockpit Mutation Wave 4A — Durable Activity Journal Handoff Implementation Decision')
        ->and($report)->toContain('does not:')
        ->and($report)->toContain('write journal entries')
        ->and($report)->toContain('execute x-action actions')
        ->and($report)->toContain('send x-feedback deliveries')
        ->and($cockpitCompass)->toContain('Cockpit Mutation Wave 3N — Durable Activity Closure / Production Readiness Decision')
        ->and($cockpitCompass)->toContain('reports/076-durable-activity-closure-production-readiness-decision.md')
        ->and($settlementCompass)->toContain('x-change Cockpit Mutation Wave 3N — Durable Activity Closure / Production Readiness Decision')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/076-durable-activity-closure-production-readiness-decision.md');
});
