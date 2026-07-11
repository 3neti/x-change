<?php

declare(strict_types=1);

it('documents cockpit mutation wave 7a durable activity authorization tenant scope enforcement baseline', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/121-wave-7a-durable-activity-authorization-tenant-scope-enforcement-baseline.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Mutation Wave 7A — Durable Activity Authorization / Tenant Scope Enforcement Baseline')
        ->and($report)->toContain('Status: Scaffolded / Baseline recorded')
        ->and($report)->toContain('Durable activity access must be authorized and tenant-scoped before production default enablement.')
        ->and($report)->toContain('Durable activity recording remains disabled by default.')
        ->and($report)->toContain('No current Cockpit UI change is expected.')
        ->and($report)->toContain('7B — Durable Activity Retention / Purge Enforcement Baseline')
        ->and($cockpitCompass)->toContain('Cockpit Mutation Wave 7A — Durable Activity Authorization / Tenant Scope Enforcement Baseline')
        ->and($cockpitCompass)->toContain('reports/121-wave-7a-durable-activity-authorization-tenant-scope-enforcement-baseline.md')
        ->and($settlementCompass)->toContain('Cockpit Mutation Wave 7A — Durable Activity Authorization / Tenant Scope Enforcement Baseline')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/121-wave-7a-durable-activity-authorization-tenant-scope-enforcement-baseline.md');
});
