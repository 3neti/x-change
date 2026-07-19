<?php

declare(strict_types=1);

it('documents distribution workspace readiness consolidation acceptance checklist slice 1', function (): void {
    $packageRoot = dirname(__DIR__, 3);

    $checklist = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/575-distribution-workspace-readiness-consolidation-acceptance-slice-1-checklist.md');
    $closure = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/576-distribution-workspace-readiness-consolidation-acceptance-slice-2-automated-closure.md');
    $humanPass = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/577-distribution-workspace-readiness-consolidation-acceptance-slice-3-human-pass.md');
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
        ->and($closure)->toContain('Distribution Workspace Readiness Consolidation Acceptance — Slice 2 Automated Closure')
        ->and($closure)->toContain('automated-green / pending-human-visual-acceptance')
        ->and($closure)->toContain('php artisan x-change:doctor --assets --no-interaction')
        ->and($closure)->toContain('php artisan dusk tests/Browser/CockpitVoucherDetailDistributionSmokeTest.php')
        ->and($closure)->toContain('Manual visual review is still required')
        ->and($humanPass)->toContain('Distribution Workspace Readiness Consolidation Acceptance — Slice 3 Human Pass')
        ->and($humanPass)->toContain('Result: `Pass`')
        ->and($humanPass)->toContain('/x/cockpit/pay-codes/E9MC/distribution')
        ->and($humanPass)->toContain('http://x-change-sandbox.test/x/claim/E9MC/experience')
        ->and($humanPass)->toContain('Detailed readiness panels')
        ->and($humanPass)->toContain('The old `Channel and artifact readiness` repeated metric grid is absent.')
        ->and($humanPass)->toContain('Visible runtime errors reported: none.')
        ->and($humanPass)->toContain('The scrape does not show feedback delivery, campaign dispatch, journal writes, provider calls, voucher mutation, wallet mutation, Treasury mutation, or money movement.')
        ->and($cockpitCompass)->toContain('Distribution Workspace Readiness Consolidation Acceptance Slice 1')
        ->and($cockpitCompass)->toContain('reports/575-distribution-workspace-readiness-consolidation-acceptance-slice-1-checklist.md')
        ->and($cockpitCompass)->toContain('Distribution Workspace Readiness Consolidation Acceptance Slice 2')
        ->and($cockpitCompass)->toContain('reports/576-distribution-workspace-readiness-consolidation-acceptance-slice-2-automated-closure.md')
        ->and($cockpitCompass)->toContain('Distribution Workspace Readiness Consolidation Acceptance Slice 3')
        ->and($cockpitCompass)->toContain('reports/577-distribution-workspace-readiness-consolidation-acceptance-slice-3-human-pass.md')
        ->and($settlementCompass)->toContain('Distribution Workspace Readiness Consolidation Acceptance — Slice 1')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/575-distribution-workspace-readiness-consolidation-acceptance-slice-1-checklist.md')
        ->and($settlementCompass)->toContain('Distribution Workspace Readiness Consolidation Acceptance — Slice 2')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/576-distribution-workspace-readiness-consolidation-acceptance-slice-2-automated-closure.md')
        ->and($settlementCompass)->toContain('Distribution Workspace Readiness Consolidation Acceptance — Slice 3')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/577-distribution-workspace-readiness-consolidation-acceptance-slice-3-human-pass.md');
});
