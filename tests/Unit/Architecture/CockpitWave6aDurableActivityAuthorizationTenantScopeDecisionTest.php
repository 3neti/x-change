<?php

declare(strict_types=1);

it('documents cockpit mutation wave 6a durable activity authorization   tenant scope decision', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/111-wave-6a-durable-activity-authorization-tenant-scope-decision.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Mutation Wave 6A — Durable Activity Authorization / Tenant Scope Decision')
        ->and($report)->toContain('Status: Scaffolded / Decision recorded')
        ->and($report)->toContain('Decide that production durable activity reads require explicit operator authorization and tenant scoping before default enablement.')
        ->and($report)->toContain('Durable activity recording remains disabled by default.')
        ->and($report)->toContain('6B — Durable Activity Retention / Purge Policy Decision')
        ->and($cockpitCompass)->toContain('Cockpit Mutation Wave 6A — Durable Activity Authorization / Tenant Scope Decision')
        ->and($cockpitCompass)->toContain('reports/111-wave-6a-durable-activity-authorization-tenant-scope-decision.md')
        ->and($settlementCompass)->toContain('Cockpit Mutation Wave 6A — Durable Activity Authorization / Tenant Scope Decision')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/111-wave-6a-durable-activity-authorization-tenant-scope-decision.md');
});
