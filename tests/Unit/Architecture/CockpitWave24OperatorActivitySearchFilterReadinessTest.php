<?php

declare(strict_types=1);

it('documents cockpit wave 24 operator activity search filter readiness', function () {
    $packageRoot = dirname(__DIR__, 3);
    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/196-wave-24-operator-activity-search-filter-readiness.md');

    expect($report)->toContain('CockpitOperatorIssuanceActivitySearchFilterData')
        ->and($report)->toContain('CockpitReadModelQueryData::operatorActivityFilters')
        ->and($report)->toContain('InMemoryCockpitOperatorIssuanceActivityRepository')
        ->and($report)->toContain('DatabaseCockpitOperatorIssuanceActivityRepository')
        ->and($report)->toContain('CockpitOperatorIssuanceActivityReadModelData::search_filters')
        ->and($report)->toContain('No visible UI change is expected in Wave 24')
        ->and($report)->toContain('runtime configuration mutation UI')
        ->and($report)->toContain('raw payload display')
        ->and($report)->toContain('Search/filter DTO: 2 passed, 9 assertions')
        ->and($report)->toContain('Repository filtering: 13 passed, 43 assertions')
        ->and($report)->toContain('Read-model filter metadata: 8 passed, 81 assertions')
        ->and($report)->toContain('Frontend suite: 76 files passed, 482 tests passed')
        ->and($report)->toContain('Cockpit Wave 25 — Operator Activity Search / Filter UI Presentation');
});
