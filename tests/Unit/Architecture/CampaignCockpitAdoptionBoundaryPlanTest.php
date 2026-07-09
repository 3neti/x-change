<?php

declare(strict_types=1);

it('documents the read-only campaign cockpit adoption boundary before host integration code', function () {
    $reportPath = dirname(__DIR__, 3).'/docs/ui-cockpit/reports/015-campaign-cockpit-adoption-boundary-plan.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);

    expect($report)->toContain('# Host Integration Slice 1A — Campaign Cockpit Adoption Boundary Plan')
        ->and($report)->toContain('Read-only Campaign Cockpit Adoption')
        ->and($report)->toContain('x-campaign Phase 15')
        ->and($report)->toContain('Cockpit consumption map')
        ->and($report)->toContain('endpoint recommendation matrix')
        ->and($report)->toContain('host mutation authorization checklist')
        ->and($report)->toContain('No campaign mutation endpoints')
        ->and($report)->toContain('No Pay Code generation through campaign')
        ->and($report)->toContain('No delivery dispatch')
        ->and($report)->toContain('No journal writes')
        ->and($report)->toContain('No feedback sends or retries')
        ->and($report)->toContain('No wallet reads or writes')
        ->and($report)->toContain('No money movement');
});

it('updates the cockpit compass for host integration slice 1A', function () {
    $compass = file_get_contents(dirname(__DIR__, 3).'/docs/ui-cockpit/COMPASS.md');

    expect($compass)->toContain('Current slice: Host Integration Slice 1A — Campaign Cockpit Adoption Boundary Plan')
        ->and($compass)->toContain('Status: Complete')
        ->and($compass)->toContain('x-campaign Phase 15 host adoption surfaces are inputs only')
        ->and($compass)->toContain('Campaign Cockpit adoption remains read-only and host-owned')
        ->and($compass)->toContain('Mutation route scaffolding remains unauthorized');
});
