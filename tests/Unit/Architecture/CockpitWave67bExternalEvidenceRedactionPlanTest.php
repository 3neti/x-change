<?php

declare(strict_types=1);

it('documents cockpit wave 67b external evidence redaction plan', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/380-wave-67b-external-evidence-redaction-plan.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Wave 67B — Manual Distribution External Evidence Redaction Plan')
        ->and($report)->toContain('Complete / Planning-only redaction baseline.')
        ->and($report)->toContain('External evidence intake must be operator-safe by default.')
        ->and($report)->toContain('Beneficiary URL')
        ->and($report)->toContain('sensitive settlement access material')
        ->and($report)->toContain('Recipient mobile/email/reference')
        ->and($report)->toContain('Mask by default')
        ->and($report)->toContain('Attachments')
        ->and($report)->toContain('Block until attachment policy')
        ->and($report)->toContain('Credentials and tokens')
        ->and($report)->toContain('Always reject.')
        ->and($report)->toContain('Render redacted evidence summaries only.')
        ->and($report)->toContain('not lifecycle truth')
        ->and($report)->toContain('Evidence text areas.')
        ->and($report)->toContain('Raw transcript storage.')
        ->and($report)->toContain('Unmasked recipient contact display.')
        ->and($report)->toContain('Cockpit Wave 67C — Manual Distribution External Evidence Authorization / Redaction Closure')
        ->and($cockpitCompass)->toContain('Cockpit Wave 67B — Manual Distribution External Evidence Redaction Plan')
        ->and($cockpitCompass)->toContain('reports/380-wave-67b-external-evidence-redaction-plan.md')
        ->and($settlementCompass)->toContain('Cockpit Wave 67B — Manual Distribution External Evidence Redaction Plan')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/380-wave-67b-external-evidence-redaction-plan.md');
});
