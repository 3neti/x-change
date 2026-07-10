<?php

declare(strict_types=1);

it('documents real operator activity rollout readiness decision', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/095-real-operator-activity-rollout-readiness-decision.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Mutation Wave 5A — Real Operator Activity Rollout Readiness Decision')
        ->and($report)->toContain('Status: Decision recorded')
        ->and($report)->toContain('Proceed to local real Quick Generate durable activity opt-in verification.')
        ->and($report)->toContain('Do not enable durable activity recording by default in production yet.')
        ->and($report)->toContain('XCHANGE_COCKPIT_OPERATOR_ISSUANCE_ACTIVITY_REPOSITORY=LBHurtado\XChange\Services\Cockpit\DatabaseCockpitOperatorIssuanceActivityRepository')
        ->and($report)->toContain('XCHANGE_COCKPIT_OPERATOR_ISSUANCE_ACTIVITY_RECORDER=LBHurtado\XChange\Services\Cockpit\DatabaseCockpitOperatorIssuanceActivityRecorder')
        ->and($report)->toContain('one real Cockpit Quick Generate issuance')
        ->and($report)->toContain('the activity row uses a generated Pay Code, not `PC-LOCAL-DIAGNOSTIC`')
        ->and($report)->toContain('raw idempotency key is not persisted')
        ->and($report)->toContain('action and feedback remain non-executing unless separately authorized')
        ->and($report)->toContain('Cockpit Mutation Wave 5B — Real Quick Generate Durable Activity Local Opt-In Verification')
        ->and($report)->toContain('This checkpoint did not add:')
        ->and($report)->toContain('money movement.')
        ->and($cockpitCompass)->toContain('Cockpit Mutation Wave 5A — Real Operator Activity Rollout Readiness Decision')
        ->and($cockpitCompass)->toContain('reports/095-real-operator-activity-rollout-readiness-decision.md')
        ->and($settlementCompass)->toContain('x-change Cockpit Mutation Wave 5A — Real Operator Activity Rollout Readiness Decision')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/095-real-operator-activity-rollout-readiness-decision.md');
});
