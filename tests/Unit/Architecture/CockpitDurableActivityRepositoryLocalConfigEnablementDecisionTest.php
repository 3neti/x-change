<?php

declare(strict_types=1);

it('documents the cockpit durable activity repository local config enablement decision', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/093-durable-activity-repository-local-config-enablement-decision.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Mutation Wave 4Q — Durable Activity Repository Local Config Enablement Decision')
        ->and($report)->toContain('Status: Enabled locally')
        ->and($report)->toContain('XCHANGE_COCKPIT_OPERATOR_ISSUANCE_ACTIVITY_REPOSITORY=LBHurtado\XChange\Services\Cockpit\DatabaseCockpitOperatorIssuanceActivityRepository')
        ->and($report)->toContain('php artisan config:clear')
        ->and($report)->toContain('Configuration cache cleared successfully')
        ->and($report)->toContain('php artisan x-change:cockpit:seed-diagnostic-activity --local-only --operator-id=5 --json')
        ->and($report)->toContain('operator_id')
        ->and($report)->toContain('actor_id: 5')
        ->and($report)->toContain('admin@disburse.cash')
        ->and($report)->toContain('local-fixture-operator')
        ->and($report)->toContain('Operator Scope Correction')
        ->and($report)->toContain('dashboard_ready')
        ->and($report)->toContain('true')
        ->and($report)->toContain('fixture-cockpit-journal-diagnostic-activity')
        ->and($report)->toContain('PC-LOCAL-DIAGNOSTIC')
        ->and($report)->toContain('ERN-LOCAL-COCKPIT-0001')
        ->and($report)->toContain('raw_payloads_exposed: false')
        ->and($report)->toContain('provider_payloads_exposed: false')
        ->and($report)->toContain('wallet_data_exposed: false')
        ->and($report)->toContain('recipient_secrets_exposed: false')
        ->and($report)->toContain('Enable the database durable activity repository locally for visual verification.')
        ->and($report)->toContain('Cockpit Mutation Wave 4R — Seeded Diagnostic Fixture Human Visual Confirmation')
        ->and($cockpitCompass)->toContain('Cockpit Mutation Wave 4Q — Durable Activity Repository Local Config Enablement Decision')
        ->and($cockpitCompass)->toContain('reports/093-durable-activity-repository-local-config-enablement-decision.md')
        ->and($settlementCompass)->toContain('x-change Cockpit Mutation Wave 4Q — Durable Activity Repository Local Config Enablement Decision')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/093-durable-activity-repository-local-config-enablement-decision.md');
});
