<?php

declare(strict_types=1);

it('documents cockpit wave 60a manual distribution link human guidance acceptance plan', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/357-wave-60a-manual-distribution-link-human-guidance-acceptance-plan.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Wave 60A — Manual Distribution Link Human Guidance Acceptance Plan')
        ->and($report)->toContain('/x/cockpit/pay-codes/{code}')
        ->and($report)->toContain('/x/cockpit/pay-codes/{code}/distribution')
        ->and($report)->toContain('Manual distribution only.')
        ->and($report)->toContain('Use an approved external workflow.')
        ->and($report)->toContain('Verify the recipient before sharing.')
        ->and($report)->toContain('Cockpit does not send SMS, email, webhook, in-app, or campaign delivery.')
        ->and($report)->toContain('Cockpit does not record copy telemetry.')
        ->and($report)->toContain('Cockpit does not create short links or QR assets.')
        ->and($report)->toContain('Beneficiary URLs are sensitive settlement access material.')
        ->and($report)->toContain('pending-human-guidance-intake')
        ->and($report)->toContain('Cockpit Wave 60B — Manual Guidance Human Evidence Record Template')
        ->and($cockpitCompass)->toContain('Cockpit Wave 60A — Manual Distribution Link Human Guidance Acceptance Plan')
        ->and($cockpitCompass)->toContain('reports/357-wave-60a-manual-distribution-link-human-guidance-acceptance-plan.md')
        ->and($settlementCompass)->toContain('Cockpit Wave 60A — Manual Distribution Link Human Guidance Acceptance Plan')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/357-wave-60a-manual-distribution-link-human-guidance-acceptance-plan.md');
});
