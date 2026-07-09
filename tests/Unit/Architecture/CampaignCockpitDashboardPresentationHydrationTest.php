<?php

declare(strict_types=1);

it('documents the campaign cockpit dashboard presentation hydration slice', function () {
    $reportPath = dirname(__DIR__, 3).'/docs/ui-cockpit/reports/019-campaign-cockpit-dashboard-presentation-hydration.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);

    expect($report)->toContain('# Host Integration Slice 1E — Campaign Cockpit Dashboard Presentation Hydration')
        ->and($report)->toContain('campaign_read_model')
        ->and($report)->toContain('read-only dashboard presentation')
        ->and($report)->toContain('No campaign mutation endpoints')
        ->and($report)->toContain('No Pay Code generation through campaign')
        ->and($report)->toContain('No delivery dispatch')
        ->and($report)->toContain('No journal writes')
        ->and($report)->toContain('No feedback sends or retries')
        ->and($report)->toContain('No wallet reads or writes')
        ->and($report)->toContain('No money movement');
});

it('updates the cockpit compass for host integration slice 1E', function () {
    $compass = file_get_contents(dirname(__DIR__, 3).'/docs/ui-cockpit/COMPASS.md');

    expect($compass)->toContain('Current slice: Host Integration Slice 1E — Campaign Cockpit Dashboard Presentation Hydration')
        ->and($compass)->toContain('Status: Complete')
        ->and($compass)->toContain('Dashboard renders `campaign_read_model` through a read-only presentation panel')
        ->and($compass)->toContain('Campaign presentation sanitizes facts before display')
        ->and($compass)->toContain('Campaign mutation route scaffolding remains unauthorized');
});
