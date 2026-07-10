<?php

declare(strict_types=1);

it('documents the cockpit durable activity read model adapter', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/073-durable-activity-read-model-adapter.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $adapter = file_get_contents($packageRoot.'/src/Services/Cockpit/DurableCockpitOperatorIssuanceActivityReadModelProvider.php');
    $provider = file_get_contents($packageRoot.'/src/Services/Cockpit/VoucherLifecycleCockpitReadModelProvider.php');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Mutation Wave 3K — Durable Activity Read Model Adapter')
        ->and($report)->toContain('Status: Implemented')
        ->and($report)->toContain('DurableCockpitOperatorIssuanceActivityReadModelProvider')
        ->and($report)->toContain('CockpitOperatorIssuanceActivityRepositoryContract')
        ->and($report)->toContain('CockpitOperatorIssuanceActivityPresenterContract')
        ->and($report)->toContain('No package Vue components, pages, routes, or TypeScript contracts were changed')
        ->and($report)->toContain('Cockpit Mutation Wave 3L — Durable Activity Dashboard Verification')
        ->and($adapter)->toContain('class DurableCockpitOperatorIssuanceActivityReadModelProvider')
        ->and($adapter)->toContain('NullCockpitOperatorIssuanceActivityRepository')
        ->and($adapter)->toContain('CockpitOperatorIssuanceActivityReadModelData')
        ->and($provider)->toContain('forOperatorIssuanceActivity')
        ->and($provider)->toContain('operatorIssuanceActivity?->forOperator($query)')
        ->and($cockpitCompass)->toContain('Cockpit Mutation Wave 3K — Durable Activity Read Model Adapter')
        ->and($cockpitCompass)->toContain('reports/073-durable-activity-read-model-adapter.md')
        ->and($settlementCompass)->toContain('x-change Cockpit Mutation Wave 3K — Durable Activity Read Model Adapter')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/073-durable-activity-read-model-adapter.md');
});
