<?php

declare(strict_types=1);

it('documents the cockpit operator issuance activity read model contract without runtime side effects', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/051-operator-issuance-activity-read-model-contract.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Mutation Wave 2A — Operator Issuance Activity Read Model Contract')
        ->and($report)->toContain('Status: Implemented')
        ->and($report)->toContain('operator issuance activity')
        ->and($report)->toContain('raw payloads')
        ->and($report)->toContain('provider payloads')
        ->and($report)->toContain('wallet data')
        ->and($report)->toContain('journal writes')
        ->and($report)->toContain('action execution')
        ->and($report)->toContain('feedback delivery')
        ->and($report)->toContain('money movement')
        ->and($cockpitCompass)->toContain('Cockpit Mutation Wave 2A — Operator Issuance Activity Read Model Contract')
        ->and($cockpitCompass)->toContain('reports/051-operator-issuance-activity-read-model-contract.md')
        ->and($settlementCompass)->toContain('x-change Cockpit Mutation Wave 2A — Operator Issuance Activity Read Model Contract')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/051-operator-issuance-activity-read-model-contract.md');
});
