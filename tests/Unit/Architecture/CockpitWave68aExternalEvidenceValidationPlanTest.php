<?php

declare(strict_types=1);

it('documents cockpit wave 68a external evidence validation plan', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/382-wave-68a-external-evidence-validation-plan.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Wave 68A — Manual Distribution External Evidence Validation Plan')
        ->and($report)->toContain('Complete / Planning-only validation baseline.')
        ->and($report)->toContain('External evidence intake must be reject-by-default.')
        ->and($report)->toContain('Pay Code reference.')
        ->and($report)->toContain('Distribution workspace reference.')
        ->and($report)->toContain('Evidence type.')
        ->and($report)->toContain('Idempotency key.')
        ->and($report)->toContain('Raw beneficiary claim URLs in free-form notes.')
        ->and($report)->toContain('Access tokens.')
        ->and($report)->toContain('Webhook payloads.')
        ->and($report)->toContain('submitted')
        ->and($report)->toContain('accepted_for_review')
        ->and($report)->toContain('needs_correction')
        ->and($report)->toContain('Evidence request classes.')
        ->and($report)->toContain('Evidence state transition handlers.')
        ->and($report)->toContain('Cockpit Wave 68B — Manual Distribution External Evidence Retention Plan')
        ->and($cockpitCompass)->toContain('Cockpit Wave 68A — Manual Distribution External Evidence Validation Plan')
        ->and($cockpitCompass)->toContain('reports/382-wave-68a-external-evidence-validation-plan.md')
        ->and($settlementCompass)->toContain('Cockpit Wave 68A — Manual Distribution External Evidence Validation Plan')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/382-wave-68a-external-evidence-validation-plan.md');
});
