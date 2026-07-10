<?php

declare(strict_types=1);

it('documents durable activity local opt in closure cleanup decision', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/098-durable-activity-local-opt-in-closure-cleanup-decision.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Mutation Wave 5D — Durable Activity Local Opt-In Closure / Cleanup Decision')
        ->and($report)->toContain('Status: Decision recorded')
        ->and($report)->toContain('Keep the local database repository and recorder enabled for continued manual Cockpit testing.')
        ->and($report)->toContain('Do not enable durable activity recording by default in production yet.')
        ->and($report)->toContain('PC-LOCAL-DIAGNOSTIC')
        ->and($report)->toContain('MCPC')
        ->and($report)->toContain('The synthetic diagnostic fixture is no longer required for the primary real-activity proof')
        ->and($report)->toContain('Track the Brick\\Math float deprecation as a separate cleanup slice.')
        ->and($report)->toContain('XCHANGE_COCKPIT_OPERATOR_ISSUANCE_ACTIVITY_REPOSITORY=LBHurtado\XChange\Services\Cockpit\DatabaseCockpitOperatorIssuanceActivityRepository')
        ->and($report)->toContain('XCHANGE_COCKPIT_OPERATOR_ISSUANCE_ACTIVITY_RECORDER=LBHurtado\XChange\Services\Cockpit\DatabaseCockpitOperatorIssuanceActivityRecorder')
        ->and($report)->toContain('This checkpoint did not add:')
        ->and($report)->toContain('database writes;')
        ->and($report)->toContain('database deletes;')
        ->and($report)->toContain('Cockpit Mutation Wave 5E — Synthetic Fixture Local Cleanup Decision / BrickMath Cleanup Planning')
        ->and($cockpitCompass)->toContain('Cockpit Mutation Wave 5D — Durable Activity Local Opt-In Closure / Cleanup Decision')
        ->and($cockpitCompass)->toContain('reports/098-durable-activity-local-opt-in-closure-cleanup-decision.md')
        ->and($settlementCompass)->toContain('x-change Cockpit Mutation Wave 5D — Durable Activity Local Opt-In Closure / Cleanup Decision')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/098-durable-activity-local-opt-in-closure-cleanup-decision.md');
});
