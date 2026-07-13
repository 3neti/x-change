<?php

declare(strict_types=1);

it('documents cockpit wave 67a external evidence authorization plan', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/379-wave-67a-external-evidence-authorization-plan.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Wave 67A — Manual Distribution External Evidence Authorization Plan')
        ->and($report)->toContain('Complete / Planning-only authorization baseline.')
        ->and($report)->toContain('External evidence intake must be denied by default.')
        ->and($report)->toContain('Authenticated operator identity.')
        ->and($report)->toContain('Evidence-create permission.')
        ->and($report)->toContain('Evidence-view permission.')
        ->and($report)->toContain('Evidence-review permission.')
        ->and($report)->toContain('Tenant scope.')
        ->and($report)->toContain('Voucher / Pay Code scope.')
        ->and($report)->toContain('Sensitive URL handling')
        ->and($report)->toContain('Incident escalation')
        ->and($report)->toContain('Evidence submission forms.')
        ->and($report)->toContain('Evidence routes.')
        ->and($report)->toContain('Evidence database tables.')
        ->and($report)->toContain('Evidence journal writes.')
        ->and($report)->toContain('Cockpit Wave 67B — Manual Distribution External Evidence Redaction Plan')
        ->and($cockpitCompass)->toContain('Cockpit Wave 67A — Manual Distribution External Evidence Authorization Plan')
        ->and($cockpitCompass)->toContain('reports/379-wave-67a-external-evidence-authorization-plan.md')
        ->and($settlementCompass)->toContain('Cockpit Wave 67A — Manual Distribution External Evidence Authorization Plan')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/379-wave-67a-external-evidence-authorization-plan.md');
});
