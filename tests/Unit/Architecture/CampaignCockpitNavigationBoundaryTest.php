<?php

declare(strict_types=1);

it('documents the campaign cockpit workspace explorer navigation boundary slice', function () {
    $reportPath = dirname(__DIR__, 3).'/docs/ui-cockpit/reports/020-campaign-cockpit-navigation-boundary.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);

    expect($report)->toContain('# Host Integration Slice 1F — Campaign Cockpit Workspace / Explorer Read-Only Navigation Boundary')
        ->and($report)->toContain('campaign_navigation_context')
        ->and($report)->toContain('existing Pay Code Explorer route')
        ->and($report)->toContain('No campaign route namespace')
        ->and($report)->toContain('No campaign mutation endpoints')
        ->and($report)->toContain('No Pay Code generation through campaign')
        ->and($report)->toContain('No delivery dispatch')
        ->and($report)->toContain('No journal writes')
        ->and($report)->toContain('No feedback sends or retries')
        ->and($report)->toContain('No wallet reads or writes')
        ->and($report)->toContain('No money movement');
});

it('updates the cockpit compass for host integration slice 1F', function () {
    $compass = file_get_contents(dirname(__DIR__, 3).'/docs/ui-cockpit/COMPASS.md');

    expect($compass)->toContain('Completed Host Integration Slice 1F — Campaign Cockpit Workspace / Explorer Read-Only Navigation Boundary')
        ->and($compass)->toContain('Dashboard campaign panel links to existing read-only Cockpit explorer context')
        ->and($compass)->toContain('Pay Code Explorer renders `campaign_navigation_context` as presentation-only context')
        ->and($compass)->toContain('Campaign mutation route scaffolding remains unauthorized');
});
