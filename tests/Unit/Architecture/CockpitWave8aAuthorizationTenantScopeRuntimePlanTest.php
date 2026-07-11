<?php

declare(strict_types=1);

it('documents cockpit mutation wave 8a authorization tenant scope runtime plan', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/131-wave-8a-authorization-tenant-scope-runtime-plan.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Mutation Wave 8A — Authorization / Tenant Scope Runtime Plan')
        ->and($report)->toContain('Status: Scaffolded / Runtime planning recorded')
        ->and($report)->toContain('Proceed with runtime authorization and tenant-scope enforcement before enabling durable activity recording by default.')
        ->and($report)->toContain('Durable activity production default remains disabled.')
        ->and($report)->toContain('No current Cockpit UI change is expected.')
        ->and($report)->toContain('8B — Durable Activity Scope DTO / Decision Contract Plan')
        ->and($cockpitCompass)->toContain('Cockpit Mutation Wave 8A — Authorization / Tenant Scope Runtime Plan')
        ->and($cockpitCompass)->toContain('reports/131-wave-8a-authorization-tenant-scope-runtime-plan.md')
        ->and($settlementCompass)->toContain('Cockpit Mutation Wave 8A — Authorization / Tenant Scope Runtime Plan')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/131-wave-8a-authorization-tenant-scope-runtime-plan.md');
});
