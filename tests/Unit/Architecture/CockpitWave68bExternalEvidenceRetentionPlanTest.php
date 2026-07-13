<?php

declare(strict_types=1);

it('documents cockpit wave 68b external evidence retention plan', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/383-wave-68b-external-evidence-retention-plan.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Wave 68B — Manual Distribution External Evidence Retention Plan')
        ->and($report)->toContain('Complete / Planning-only retention baseline.')
        ->and($report)->toContain('do-not-store-until-retention-policy-is-approved')
        ->and($report)->toContain('Redacted evidence summary')
        ->and($report)->toContain('Raw beneficiary URLs')
        ->and($report)->toContain('Attachments')
        ->and($report)->toContain('Provider payloads')
        ->and($report)->toContain('Credentials and tokens')
        ->and($report)->toContain('Retention duration.')
        ->and($report)->toContain('Automatic purge schedule.')
        ->and($report)->toContain('Legal hold behavior.')
        ->and($report)->toContain('Show only current redacted evidence summaries by default.')
        ->and($report)->toContain('Evidence tables.')
        ->and($report)->toContain('Evidence purge jobs.')
        ->and($report)->toContain('Attachment storage.')
        ->and($report)->toContain('Cockpit Wave 68C — Manual Distribution External Evidence Validation / Retention Closure')
        ->and($cockpitCompass)->toContain('Cockpit Wave 68B — Manual Distribution External Evidence Retention Plan')
        ->and($cockpitCompass)->toContain('reports/383-wave-68b-external-evidence-retention-plan.md')
        ->and($settlementCompass)->toContain('Cockpit Wave 68B — Manual Distribution External Evidence Retention Plan')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/383-wave-68b-external-evidence-retention-plan.md');
});
