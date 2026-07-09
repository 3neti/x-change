<?php

declare(strict_types=1);

it('documents the campaign cockpit optional adapter boundary slice', function () {
    $reportPath = dirname(__DIR__, 3).'/docs/ui-cockpit/reports/017-campaign-cockpit-optional-adapter-boundary.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);

    expect($report)->toContain('# Host Integration Slice 1C — Campaign Cockpit Read Model Optional Adapter Boundary')
        ->and($report)->toContain('CampaignCockpitWorkspace::summary')
        ->and($report)->toContain('configured service ID')
        ->and($report)->toContain('No hard Composer dependency on `x-campaign`')
        ->and($report)->toContain('No x-campaign imports')
        ->and($report)->toContain('No campaign mutation endpoints')
        ->and($report)->toContain('No Pay Code generation through campaign')
        ->and($report)->toContain('No delivery dispatch')
        ->and($report)->toContain('No journal writes')
        ->and($report)->toContain('No feedback sends or retries')
        ->and($report)->toContain('No wallet reads or writes')
        ->and($report)->toContain('No money movement');
});

it('updates the cockpit compass for host integration slice 1C', function () {
    $compass = file_get_contents(dirname(__DIR__, 3).'/docs/ui-cockpit/COMPASS.md');

    expect($compass)->toContain('Completed Host Integration Slice 1C — Campaign Cockpit Read Model Optional Adapter Boundary')
        ->and($compass)->toContain('Status: Complete')
        ->and($compass)->toContain('Optional campaign Cockpit adapter boundary exists')
        ->and($compass)->toContain('Campaign adapter resolution remains string-configured and fail-safe')
        ->and($compass)->toContain('Campaign mutation route scaffolding remains unauthorized');
});
