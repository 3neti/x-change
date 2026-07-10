<?php

declare(strict_types=1);

it('documents the seeded diagnostic fixture host verification and human visual handoff', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/092-seeded-diagnostic-fixture-host-verification-human-visual-handoff.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Mutation Wave 4P — Seeded Diagnostic Fixture Host Verification / Human Visual Handoff')
        ->and($report)->toContain('Status: Blocked — host read model repository config is not enabled')
        ->and($report)->toContain('http://x-change-sandbox.test/x/cockpit')
        ->and($report)->toContain('x-change:cockpit:seed-diagnostic-activity')
        ->and($report)->toContain('x-change.cockpit.operator_issuance_activity.repository ................ null')
        ->and($report)->toContain('checked 55, ok 55, stale 0, missing 0, extra 0')
        ->and($report)->toContain('"dashboard_ready": false')
        ->and($report)->toContain('"dashboard_repository": null')
        ->and($report)->toContain('fixture-cockpit-journal-diagnostic-activity')
        ->and($report)->toContain('PC-LOCAL-DIAGNOSTIC')
        ->and($report)->toContain('ERN-LOCAL-COCKPIT-0001')
        ->and($report)->toContain('cockpit.operator_issuance_activity.fixture')
        ->and($report)->toContain('XCHANGE_COCKPIT_OPERATOR_ISSUANCE_ACTIVITY_REPOSITORY=LBHurtado\XChange\Services\Cockpit\DatabaseCockpitOperatorIssuanceActivityRepository')
        ->and($report)->toContain('Cockpit Mutation Wave 4Q — Durable Activity Repository Local Config Enablement Decision')
        ->and($cockpitCompass)->toContain('Cockpit Mutation Wave 4P — Seeded Diagnostic Fixture Host Verification / Human Visual Handoff')
        ->and($cockpitCompass)->toContain('reports/092-seeded-diagnostic-fixture-host-verification-human-visual-handoff.md')
        ->and($settlementCompass)->toContain('x-change Cockpit Mutation Wave 4P — Seeded Diagnostic Fixture Host Verification / Human Visual Handoff')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/092-seeded-diagnostic-fixture-host-verification-human-visual-handoff.md');
});
