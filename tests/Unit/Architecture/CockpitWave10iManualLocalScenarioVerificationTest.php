<?php

declare(strict_types=1);

it('documents cockpit wave 10i manual local scenario verification handoff', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/159-wave-10i-manual-local-scenario-verification.md';

    $report = file_get_contents($reportPath);

    expect($report)->toContain('Status')
        ->and($report)->toContain('Pending human/local verification')
        ->and($report)->toContain('/x/cockpit/quick-generate')
        ->and($report)->toContain('Generate Pay Code')
        ->and($report)->toContain('preflight.pricing')
        ->and($report)->toContain('preflight.funding')
        ->and($report)->toContain('activity')
        ->and($report)->toContain('No raw payloads')
        ->and($report)->toContain('No campaign mutation');
});
