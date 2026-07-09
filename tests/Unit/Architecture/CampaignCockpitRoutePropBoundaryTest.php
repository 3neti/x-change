<?php

declare(strict_types=1);

it('documents the campaign cockpit route prop boundary slice', function () {
    $reportPath = dirname(__DIR__, 3).'/docs/ui-cockpit/reports/018-campaign-cockpit-route-prop-boundary.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);

    expect($report)->toContain('# Host Integration Slice 1D — Campaign Cockpit Read Model Route Prop Boundary')
        ->and($report)->toContain('campaign_read_model')
        ->and($report)->toContain('campaign_planning_key')
        ->and($report)->toContain('campaign_execution_id')
        ->and($report)->toContain('read-only Inertia prop')
        ->and($report)->toContain('No campaign mutation endpoints')
        ->and($report)->toContain('No Pay Code generation through campaign')
        ->and($report)->toContain('No delivery dispatch')
        ->and($report)->toContain('No journal writes')
        ->and($report)->toContain('No feedback sends or retries')
        ->and($report)->toContain('No wallet reads or writes')
        ->and($report)->toContain('No money movement');
});

it('updates the cockpit compass for host integration slice 1D', function () {
    $compass = file_get_contents(dirname(__DIR__, 3).'/docs/ui-cockpit/COMPASS.md');

    expect($compass)->toContain('Completed Host Integration Slice 1D — Campaign Cockpit Read Model Route Prop Boundary')
        ->and($compass)->toContain('Dashboard route exposes `campaign_read_model` as a read-only Inertia prop')
        ->and($compass)->toContain('Campaign context is optional and query-derived')
        ->and($compass)->toContain('Campaign mutation route scaffolding remains unauthorized');
});
