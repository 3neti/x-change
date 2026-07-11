<?php

declare(strict_types=1);

it('documents cockpit mutation wave 8j runtime enforcement parity planning closure', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/140-wave-8j-runtime-enforcement-parity-planning-closure.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Mutation Wave 8J — Runtime Enforcement / Parity Planning Closure')
        ->and($report)->toContain('Status: Scaffolded / Runtime planning recorded')
        ->and($report)->toContain('Wave 9 should start with a route/data/component parity audit for `/x/dashboard`, `/x/pay-codes`, and `/x/balances` before visible replacement work.')
        ->and($report)->toContain('Durable activity production default remains disabled.')
        ->and($report)->toContain('No current Cockpit UI change is expected.')
        ->and($report)->toContain('Wave 9A — /x/dashboard, /x/pay-codes, and /x/balances Parity Audit')
        ->and($cockpitCompass)->toContain('Cockpit Mutation Wave 8J — Runtime Enforcement / Parity Planning Closure')
        ->and($cockpitCompass)->toContain('reports/140-wave-8j-runtime-enforcement-parity-planning-closure.md')
        ->and($settlementCompass)->toContain('Cockpit Mutation Wave 8J — Runtime Enforcement / Parity Planning Closure')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/140-wave-8j-runtime-enforcement-parity-planning-closure.md');
});
