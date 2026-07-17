<?php

declare(strict_types=1);

it('documents cockpit funding and balance vocabulary before UI changes', function () {
    $packageRoot = dirname(__DIR__, 3);
    $reportPath = $packageRoot.'/docs/ui-cockpit/reports/456-funding-balance-ui-wave-slice-1-vocabulary.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)->toContain('Cockpit Funding / Balance UI Wave — Slice 1 — Vocabulary')
        ->and($report)->toContain('Internal Balance')
        ->and($report)->toContain('Outstanding Pay Codes')
        ->and($report)->toContain('Usable Balance')
        ->and($report)->toContain('Live Balance')
        ->and($report)->toContain('Use `estimate` for outstanding and usable values')
        ->and($report)->toContain('Avoid the term `reserved`')
        ->and($report)->toContain('hasTreasuryFacts = false')
        ->and($report)->toContain('treasury_facts = absent')
        ->and($report)->toContain('No Vue component behavior changed')
        ->and($cockpitCompass)->toContain('Cockpit Funding / Balance UI Wave — Slice 1 — Vocabulary')
        ->and($cockpitCompass)->toContain('reports/456-funding-balance-ui-wave-slice-1-vocabulary.md')
        ->and($settlementCompass)->toContain('Cockpit Funding / Balance UI Wave — Slice 1 — Vocabulary')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/456-funding-balance-ui-wave-slice-1-vocabulary.md');
});
