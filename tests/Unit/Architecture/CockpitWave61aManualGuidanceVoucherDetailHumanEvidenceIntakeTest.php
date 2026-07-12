<?php

declare(strict_types=1);

it('documents cockpit wave 61a manual guidance voucher detail human evidence intake', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/361-wave-61a-manual-guidance-voucher-detail-human-evidence-intake.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Wave 61A — Manual Guidance Voucher Detail Human Evidence Intake')
        ->and($report)->toContain('Completed on 2026-07-13 / Partial acceptance evidence recorded.')
        ->and($report)->toContain('Final decision supplied by reviewer: Pass')
        ->and($report)->toContain('Pay Code inspected: 6LGM')
        ->and($report)->toContain('Surface inspected: Voucher Detail')
        ->and($report)->toContain('Beneficiary URL shown: http://x-change-sandbox.test/x/claim/6LGM/experience')
        ->and($report)->toContain('Distribution Workspace evidence: pending')
        ->and($report)->toContain('Manual distribution guidance')
        ->and($report)->toContain('Use this copied link for manual distribution only.')
        ->and($report)->toContain('approved external workflow after verifying the recipient')
        ->and($report)->toContain('Cockpit does not send SMS, email, webhook, in-app notification, or campaign delivery from this panel.')
        ->and($report)->toContain('Cockpit does not record copy telemetry, create short links, or generate QR assets here.')
        ->and($report)->toContain('Treat this beneficiary URL as sensitive settlement access material.')
        ->and($report)->toContain('partial-pass / distribution-workspace-pending')
        ->and($report)->toContain('/x/cockpit/pay-codes/6LGM/distribution')
        ->and($report)->toContain('Cockpit Wave 61B — Manual Guidance Distribution Workspace Human Evidence Intake')
        ->and($cockpitCompass)->toContain('Cockpit Wave 61A — Manual Guidance Voucher Detail Human Evidence Intake')
        ->and($cockpitCompass)->toContain('reports/361-wave-61a-manual-guidance-voucher-detail-human-evidence-intake.md')
        ->and($settlementCompass)->toContain('Cockpit Wave 61A — Manual Guidance Voucher Detail Human Evidence Intake')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/361-wave-61a-manual-guidance-voucher-detail-human-evidence-intake.md');
});
