<?php

declare(strict_types=1);

it('documents cockpit wave 57c pending human intake status record', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/348-wave-57c-pending-human-intake-status-record.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);

    expect($report)->toContain('Cockpit Wave 57C — Pending Human Intake Status Record')
        ->and($report)->toContain('pending-human-intake')
        ->and($report)->toContain('No human browser acceptance evidence has been supplied')
        ->and($report)->toContain('Evidence Still Needed')
        ->and($report)->toContain('Not allowed based solely on pending intake')
        ->and($report)->toContain('Cockpit Wave 57D — Beneficiary URL Copy Acceptance Intake Closure');
});
