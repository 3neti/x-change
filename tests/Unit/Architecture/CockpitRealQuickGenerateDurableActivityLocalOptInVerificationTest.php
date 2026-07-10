<?php

declare(strict_types=1);

it('documents real quick generate durable activity local opt in verification', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/096-real-quick-generate-durable-activity-local-opt-in-verification.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Mutation Wave 5B — Real Quick Generate Durable Activity Local Opt-In Verification')
        ->and($report)->toContain('Status: Verified locally')
        ->and($report)->toContain('XCHANGE_COCKPIT_OPERATOR_ISSUANCE_ACTIVITY_REPOSITORY=LBHurtado\XChange\Services\Cockpit\DatabaseCockpitOperatorIssuanceActivityRepository')
        ->and($report)->toContain('XCHANGE_COCKPIT_OPERATOR_ISSUANCE_ACTIVITY_RECORDER=LBHurtado\XChange\Services\Cockpit\DatabaseCockpitOperatorIssuanceActivityRecorder')
        ->and($report)->toContain('checked: 55')
        ->and($report)->toContain('ok: 55')
        ->and($report)->toContain('POST /x/cockpit/quick-generate')
        ->and($report)->toContain('admin@disburse.cash')
        ->and($report)->toContain('cockpit-real-activity-5b-20260711')
        ->and($report)->toContain('corr-cockpit-real-activity-5b')
        ->and($report)->toContain('result.code: MCPC')
        ->and($report)->toContain('subject_reference: MCPC')
        ->and($report)->toContain('journal_handoff_status: not_wired')
        ->and($report)->toContain('action_handoff_status: not_wired')
        ->and($report)->toContain('feedback_handoff_status: not_wired')
        ->and($report)->toContain('raw_payloads_exposed')
        ->and($report)->toContain('false')
        ->and($report)->toContain('The raw idempotency key was not persisted.')
        ->and($report)->toContain('first_title: Pay Code MCPC issued')
        ->and($report)->toContain('Pass — local real Quick Generate durable activity opt-in verified.')
        ->and($report)->toContain('Passing floats to BigNumber::of()')
        ->and($report)->toContain('Cockpit Mutation Wave 5C — Real Durable Activity Human Visual Confirmation')
        ->and($cockpitCompass)->toContain('Cockpit Mutation Wave 5B — Real Quick Generate Durable Activity Local Opt-In Verification')
        ->and($cockpitCompass)->toContain('reports/096-real-quick-generate-durable-activity-local-opt-in-verification.md')
        ->and($settlementCompass)->toContain('x-change Cockpit Mutation Wave 5B — Real Quick Generate Durable Activity Local Opt-In Verification')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/096-real-quick-generate-durable-activity-local-opt-in-verification.md');
});
