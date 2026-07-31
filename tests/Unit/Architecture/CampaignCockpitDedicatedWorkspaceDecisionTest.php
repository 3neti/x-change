<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

it('documents the campaign cockpit dedicated read-only workspace decision point', function () {
    $reportPath = dirname(__DIR__, 3).'/docs/ui-cockpit/reports/021-campaign-cockpit-dedicated-workspace-decision.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);

    expect($report)->toContain('# Host Integration Slice 1G — Campaign Cockpit Dedicated Read-Only Workspace Decision Point')
        ->and($report)->toContain('Decision: defer a dedicated Campaign Cockpit workspace route')
        ->and($report)->toContain('existing Pay Code Explorer route remains the host navigation surface')
        ->and($report)->toContain('No dedicated campaign workspace route')
        ->and($report)->toContain('No campaign route namespace')
        ->and($report)->toContain('No campaign mutation endpoints')
        ->and($report)->toContain('No Pay Code generation through campaign')
        ->and($report)->toContain('No delivery dispatch')
        ->and($report)->toContain('No journal writes')
        ->and($report)->toContain('No feedback sends or retries')
        ->and($report)->toContain('No wallet reads or writes')
        ->and($report)->toContain('No money movement');
});

it('updates the cockpit compass for host integration slice 1G', function () {
    $compass = file_get_contents(dirname(__DIR__, 3).'/docs/ui-cockpit/COMPASS.md');

    expect($compass)->toContain('Completed Host Integration Slice 1G — Campaign Cockpit Dedicated Read-Only Workspace Decision Point')
        ->and($compass)->toContain('Dedicated Campaign Cockpit workspace route is deferred')
        ->and($compass)->toContain('Existing Pay Code Explorer remains the read-only campaign navigation surface')
        ->and($compass)->toContain('Campaign mutation route scaffolding remains unauthorized');
});

it('preserves the historical decision report while registering the subsequently authorized campaign workspace', function () {
    $campaignCockpitRoutes = collect(Route::getRoutes())
        ->filter(fn ($route): bool => str_starts_with((string) $route->getName(), 'x-change.cockpit.campaign'));

    expect($campaignCockpitRoutes)->not->toBeEmpty()
        ->and(Route::has('x-change.cockpit.campaigns.index'))->toBeTrue()
        ->and(Route::has('x-change.cockpit.campaigns.show'))->toBeTrue();
});

it('ships the authorized campaign workspace controllers and pages', function () {
    $root = dirname(__DIR__, 3);

    expect(file_exists($root.'/src/Http/Controllers/Web/Cockpit/CockpitCampaignWorksheetController.php'))->toBeTrue()
        ->and(file_exists($root.'/resources/js/cockpit/pages/Campaigns.vue'))->toBeTrue()
        ->and(file_exists($root.'/resources/js/cockpit/pages/CampaignWorksheet.vue'))->toBeTrue();
});
