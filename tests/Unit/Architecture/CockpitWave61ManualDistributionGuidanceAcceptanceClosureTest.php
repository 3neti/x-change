<?php

declare(strict_types=1);

it('documents cockpit wave 61 manual distribution guidance acceptance closure', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/363-wave-61-manual-distribution-guidance-acceptance-closure.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Wave 61 — Manual Distribution Guidance Acceptance Closure')
        ->and($report)->toContain('Complete / Pass.')
        ->and($report)->toContain('Pay Code inspected: 6LGM')
        ->and($report)->toContain('Surface inspected: Voucher Detail')
        ->and($report)->toContain('Surface inspected: Distribution Workspace')
        ->and($report)->toContain('Beneficiary URL shown: http://x-change-sandbox.test/x/claim/6LGM/experience')
        ->and($report)->toContain('reports/361-wave-61a-manual-guidance-voucher-detail-human-evidence-intake.md')
        ->and($report)->toContain('reports/362-wave-61b-manual-guidance-distribution-workspace-human-evidence-intake.md')
        ->and($report)->toContain('Use the copied link for manual distribution only.')
        ->and($report)->toContain('Share it only through an approved external workflow after verifying the recipient.')
        ->and($report)->toContain('Cockpit does not send SMS, email, webhook, in-app notification, or campaign delivery from the panel.')
        ->and($report)->toContain('Cockpit does not record copy telemetry.')
        ->and($report)->toContain('Cockpit does not create short links.')
        ->and($report)->toContain('Cockpit does not generate QR assets.')
        ->and($report)->toContain('The beneficiary URL is sensitive settlement access material.')
        ->and($report)->toContain('Manual distribution guidance is accepted')
        ->and($report)->toContain('Cockpit Wave 62 — Manual Distribution Link Operational Readiness / Next Capability Decision')
        ->and($cockpitCompass)->toContain('Cockpit Wave 61 — Manual Distribution Guidance Acceptance Closure')
        ->and($cockpitCompass)->toContain('reports/363-wave-61-manual-distribution-guidance-acceptance-closure.md')
        ->and($settlementCompass)->toContain('Cockpit Wave 61 — Manual Distribution Guidance Acceptance Closure')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/363-wave-61-manual-distribution-guidance-acceptance-closure.md');
});
