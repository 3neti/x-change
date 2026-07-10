<?php

declare(strict_types=1);

it('documents the cockpit activity redaction and retention policy contracts slice', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/066-activity-redaction-retention-policy-contracts.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Mutation Wave 3D — Activity Redaction and Retention Policy Contracts')
        ->and($report)->toContain('Status: Implemented')
        ->and($report)->toContain('CockpitOperatorIssuanceActivityRedactionPolicyContract')
        ->and($report)->toContain('CockpitOperatorIssuanceActivityRetentionPolicyContract')
        ->and($report)->toContain('DefaultCockpitOperatorIssuanceActivityRedactionPolicy')
        ->and($report)->toContain('DefaultCockpitOperatorIssuanceActivityRetentionPolicy')
        ->and($report)->toContain('No UI was changed in this slice')
        ->and($report)->toContain('No migrations were introduced')
        ->and($report)->toContain('No database writes were introduced')
        ->and($report)->toContain('Cockpit Mutation Wave 3E — Database Migration Decision Point')
        ->and($cockpitCompass)->toContain('Cockpit Mutation Wave 3D — Activity Redaction and Retention Policy Contracts')
        ->and($cockpitCompass)->toContain('reports/066-activity-redaction-retention-policy-contracts.md')
        ->and($settlementCompass)->toContain('x-change Cockpit Mutation Wave 3D — Activity Redaction and Retention Policy Contracts')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/066-activity-redaction-retention-policy-contracts.md');
});
