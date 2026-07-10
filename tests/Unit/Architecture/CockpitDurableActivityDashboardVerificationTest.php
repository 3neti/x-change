<?php

declare(strict_types=1);

it('documents the cockpit durable activity dashboard verification checkpoint', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/074-durable-activity-dashboard-verification.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $verification = file_get_contents($packageRoot.'/tests/Feature/Cockpit/CockpitOperatorIssuanceActivityDashboardVerificationTest.php');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Mutation Wave 3L — Durable Activity Dashboard Verification')
        ->and($report)->toContain('Status: Implemented')
        ->and($report)->toContain('No production source changes were required')
        ->and($report)->toContain('operator_issuance_activity_read_model')
        ->and($report)->toContain('No Vue components, pages, routes, TypeScript contracts, package assets, or host-published assets were changed')
        ->and($report)->toContain('Cockpit Mutation Wave 3M — Durable Activity Host Publish / Manual Verification')
        ->and($verification)->toContain('props.operator_issuance_activity_read_model.status')
        ->and($verification)->toContain('assertJsonMissingPath')
        ->and($cockpitCompass)->toContain('Cockpit Mutation Wave 3L — Durable Activity Dashboard Verification')
        ->and($cockpitCompass)->toContain('reports/074-durable-activity-dashboard-verification.md')
        ->and($settlementCompass)->toContain('x-change Cockpit Mutation Wave 3L — Durable Activity Dashboard Verification')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/074-durable-activity-dashboard-verification.md');
});
