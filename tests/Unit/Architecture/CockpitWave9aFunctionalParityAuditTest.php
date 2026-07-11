<?php

declare(strict_types=1);

it('documents cockpit wave 9a functional parity audit', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/141-wave-9a-functional-parity-audit.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Wave 9A — Functional Parity Audit for `/x/dashboard`, `/x/pay-codes`, and `/x/balances`')
        ->and($report)->toContain('This audit is functional, not visual.')
        ->and($report)->toContain('Cockpit already generates Pay Codes through the real x-change `GeneratePayCode` action.')
        ->and($report)->toContain('Template/Campaign Issuance Compiler')
        ->and($report)->toContain('Cockpit Wave 9B — Template/Campaign Issuance Draft Contract Baseline')
        ->and($report)->toContain('BuildBalanceOverview')
        ->and($report)->toContain('/x/dashboard')
        ->and($report)->toContain('/x/pay-codes')
        ->and($report)->toContain('/x/balances')
        ->and($cockpitCompass)->toContain('Cockpit Wave 9A — Functional Parity Audit')
        ->and($cockpitCompass)->toContain('reports/141-wave-9a-functional-parity-audit.md')
        ->and($settlementCompass)->toContain('Cockpit Wave 9A — Functional Parity Audit')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/141-wave-9a-functional-parity-audit.md');
});
