<?php

declare(strict_types=1);

it('documents distribution workspace copy acceptance checklist slice 1', function (): void {
    $packageRoot = dirname(__DIR__, 3);

    $checklist = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/570-distribution-workspace-copy-acceptance-slice-1-checklist.md');
    $browserTest = file_get_contents($packageRoot.'/../../tests/Browser/CockpitVoucherDetailDistributionSmokeTest.php');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($checklist)
        ->toContain('Distribution Workspace Copy Acceptance — Slice 1 Checklist')
        ->toContain('/x/cockpit/pay-codes/{code}/distribution')
        ->toContain('Notification channels')
        ->toContain('Printable handout options')
        ->toContain('Share options')
        ->toContain('Status evidence')
        ->toContain('Record `Pass` only after human evidence')
        ->toContain('does not change runtime behavior')
        ->and($browserTest)->toContain('NOTIFICATION CHANNELS')
        ->and($browserTest)->toContain('Message and follow-up readiness')
        ->and($browserTest)->toContain('Printable handout options')
        ->and($browserTest)->toContain('SHARE OPTIONS')
        ->and($browserTest)->toContain('STATUS EVIDENCE')
        ->and($cockpitCompass)->toContain('Distribution Workspace Copy Acceptance Slice 1')
        ->and($cockpitCompass)->toContain('reports/570-distribution-workspace-copy-acceptance-slice-1-checklist.md')
        ->and($settlementCompass)->toContain('Distribution Workspace Copy Acceptance — Slice 1')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/570-distribution-workspace-copy-acceptance-slice-1-checklist.md');
});
