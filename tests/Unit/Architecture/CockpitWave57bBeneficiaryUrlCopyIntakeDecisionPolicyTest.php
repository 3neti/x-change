<?php

declare(strict_types=1);

it('documents cockpit wave 57b beneficiary url copy intake decision policy', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/347-wave-57b-beneficiary-url-copy-intake-decision-policy.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);

    expect($report)->toContain('Cockpit Wave 57B — Beneficiary URL Copy Intake Decision Policy')
        ->and($report)->toContain('### Pass')
        ->and($report)->toContain('### Blocked')
        ->and($report)->toContain('### Fail')
        ->and($report)->toContain('Do not mark the wave as accepted without explicit human evidence')
        ->and($report)->toContain('pending-human-intake')
        ->and($report)->toContain('Cockpit Wave 57C — Pending Human Intake Status Record');
});
