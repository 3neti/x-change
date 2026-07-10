<?php

declare(strict_types=1);

it('documents the cockpit durable activity journal handoff operator diagnostics human visual confirmation record', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/089-durable-activity-journal-handoff-operator-diagnostics-human-visual-confirmation-record.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Mutation Wave 4M — Durable Activity Journal Handoff Operator Diagnostics Human Visual Confirmation Record')
        ->and($report)->toContain('Status: Blocked — no durable activity data available')
        ->and($report)->toContain('http://x-change-sandbox.test/x/cockpit')
        ->and($report)->toContain('Human Visual Confirmation Form')
        ->and($report)->toContain('Operator diagnostic')
        ->and($report)->toContain('No retry button or mutation control is visible')
        ->and($report)->toContain('No raw payload, provider payload, wallet data, secret, token, credential, OTP, or recipient secret is visible')
        ->and($report)->toContain('Blocked — no durable activity data available')
        ->and($report)->toContain('No operator issuance activity available')
        ->and($report)->toContain('Activity recording is not wired yet')
        ->and($report)->toContain('safe local durable operator issuance activity record with journal handoff diagnostic metadata')
        ->and($report)->toContain('Pass — accepted by human')
        ->and($report)->toContain('Blocked — with blocker')
        ->and($report)->toContain('Fail — with defect')
        ->and($cockpitCompass)->toContain('Cockpit Mutation Wave 4M — Durable Activity Journal Handoff Operator Diagnostics Human Visual Confirmation Record')
        ->and($cockpitCompass)->toContain('reports/089-durable-activity-journal-handoff-operator-diagnostics-human-visual-confirmation-record.md')
        ->and($settlementCompass)->toContain('x-change Cockpit Mutation Wave 4M — Durable Activity Journal Handoff Operator Diagnostics Human Visual Confirmation Record')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/089-durable-activity-journal-handoff-operator-diagnostics-human-visual-confirmation-record.md');
});
