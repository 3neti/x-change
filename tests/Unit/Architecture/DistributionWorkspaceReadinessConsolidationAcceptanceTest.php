<?php

declare(strict_types=1);

it('documents distribution workspace readiness consolidation acceptance checklist slice 1', function (): void {
    $packageRoot = dirname(__DIR__, 3);

    $checklist = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/575-distribution-workspace-readiness-consolidation-acceptance-slice-1-checklist.md');
    $browserTest = file_get_contents($packageRoot.'/../../tests/Browser/CockpitVoucherDetailDistributionSmokeTest.php');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($checklist)
        ->toContain('Distribution Workspace Readiness Consolidation Acceptance — Slice 1 Checklist')
        ->toContain('/x/cockpit/pay-codes/{code}/distribution')
        ->toContain('Detailed readiness panels')
        ->toContain('The old `Channel and artifact readiness` repeated metric grid should no longer appear.')
        ->toContain('Record `Pass` only after human evidence')
        ->and($browserTest)->toContain('DETAILED READINESS PANELS')
        ->and($browserTest)->toContain('Channel and artifact readiness')
        ->and($browserTest)->toContain('assertDontSee')
        ->and($cockpitCompass)->toContain('Distribution Workspace Readiness Consolidation Acceptance Slice 1')
        ->and($cockpitCompass)->toContain('reports/575-distribution-workspace-readiness-consolidation-acceptance-slice-1-checklist.md')
        ->and($settlementCompass)->toContain('Distribution Workspace Readiness Consolidation Acceptance — Slice 1')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/575-distribution-workspace-readiness-consolidation-acceptance-slice-1-checklist.md');
});
