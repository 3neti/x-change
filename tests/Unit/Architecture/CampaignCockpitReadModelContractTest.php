<?php

declare(strict_types=1);

it('documents the campaign cockpit read model contract slice', function () {
    $reportPath = dirname(__DIR__, 3).'/docs/ui-cockpit/reports/016-campaign-cockpit-read-model-contract.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);

    expect($report)->toContain('# Host Integration Slice 1B — Campaign Cockpit Read Model Contract')
        ->and($report)->toContain('x-change.cockpit.campaign-adoption.v1')
        ->and($report)->toContain('NullCockpitReadModelProvider')
        ->and($report)->toContain('forCampaignAdoption')
        ->and($report)->toContain('No x-campaign adapter')
        ->and($report)->toContain('No campaign mutation endpoints')
        ->and($report)->toContain('No Pay Code generation through campaign')
        ->and($report)->toContain('No delivery dispatch')
        ->and($report)->toContain('No journal writes')
        ->and($report)->toContain('No feedback sends or retries')
        ->and($report)->toContain('No wallet reads or writes')
        ->and($report)->toContain('No money movement');
});

it('updates the cockpit compass for host integration slice 1B', function () {
    $compass = file_get_contents(dirname(__DIR__, 3).'/docs/ui-cockpit/COMPASS.md');

    expect($compass)->toContain('Completed Host Integration Slice 1B — Campaign Cockpit Read Model Contract')
        ->and($compass)->toContain('Status: Complete')
        ->and($compass)->toContain('Campaign Cockpit read model contract exists in x-change')
        ->and($compass)->toContain('Default campaign adoption read model is null/not-wired')
        ->and($compass)->toContain('Optional x-campaign adapter remains deferred');
});
