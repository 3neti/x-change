<?php

declare(strict_types=1);

it('documents distribution workspace copy acceptance checklist slice 1', function (): void {
    $packageRoot = dirname(__DIR__, 3);

    $checklist = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/570-distribution-workspace-copy-acceptance-slice-1-checklist.md');
    $closure = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/571-distribution-workspace-copy-acceptance-slice-2-automated-closure.md');
    $humanPass = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/572-distribution-workspace-copy-acceptance-slice-3-human-pass.md');
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
        ->and($closure)->toContain('Distribution Workspace Copy Acceptance — Slice 2 Automated Closure')
        ->and($closure)->toContain('automated-green / pending-human-visual-acceptance')
        ->and($closure)->toContain('php artisan x-change:doctor --assets --no-interaction')
        ->and($closure)->toContain('php artisan dusk tests/Browser/CockpitVoucherDetailDistributionSmokeTest.php')
        ->and($closure)->toContain('Manual visual review is still required')
        ->and($humanPass)->toContain('Distribution Workspace Copy Acceptance — Slice 3 Human Pass')
        ->and($humanPass)->toContain('Pass with UI follow-up')
        ->and($humanPass)->toContain('/x/cockpit/pay-codes/E9MC/distribution')
        ->and($humanPass)->toContain('http://x-change-sandbox.test/x/claim/E9MC/experience')
        ->and($humanPass)->toContain('Visible runtime errors reported: none')
        ->and($humanPass)->toContain('Distribution Workspace Readiness Consolidation')
        ->and($cockpitCompass)->toContain('Distribution Workspace Copy Acceptance Slice 1')
        ->and($cockpitCompass)->toContain('reports/570-distribution-workspace-copy-acceptance-slice-1-checklist.md')
        ->and($cockpitCompass)->toContain('Distribution Workspace Copy Acceptance Slice 2')
        ->and($cockpitCompass)->toContain('reports/571-distribution-workspace-copy-acceptance-slice-2-automated-closure.md')
        ->and($cockpitCompass)->toContain('Distribution Workspace Copy Acceptance Slice 3')
        ->and($cockpitCompass)->toContain('reports/572-distribution-workspace-copy-acceptance-slice-3-human-pass.md')
        ->and($settlementCompass)->toContain('Distribution Workspace Copy Acceptance — Slice 1')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/570-distribution-workspace-copy-acceptance-slice-1-checklist.md')
        ->and($settlementCompass)->toContain('Distribution Workspace Copy Acceptance — Slice 2')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/571-distribution-workspace-copy-acceptance-slice-2-automated-closure.md')
        ->and($settlementCompass)->toContain('Distribution Workspace Copy Acceptance — Slice 3')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/572-distribution-workspace-copy-acceptance-slice-3-human-pass.md');
});
