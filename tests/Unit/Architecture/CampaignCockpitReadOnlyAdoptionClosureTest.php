<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

it('documents campaign cockpit read-only adoption closure', function () {
    $reportPath = dirname(__DIR__, 3).'/docs/ui-cockpit/reports/022-campaign-cockpit-read-only-adoption-closure.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);

    expect($report)->toContain('# Host Integration Slice 1H — Campaign Cockpit Read-Only Adoption Closure / Integration Readiness Update')
        ->and($report)->toContain('Read-only Campaign Cockpit adoption is closed through Slice 1G')
        ->and($report)->toContain('Safe operator surfaces')
        ->and($report)->toContain('Still blocked')
        ->and($report)->toContain('Dashboard campaign adoption panel')
        ->and($report)->toContain('Pay Code Explorer campaign navigation context')
        ->and($report)->toContain('No dedicated campaign workspace route')
        ->and($report)->toContain('No campaign mutation endpoints')
        ->and($report)->toContain('No Pay Code generation through campaign')
        ->and($report)->toContain('No delivery dispatch')
        ->and($report)->toContain('No journal writes')
        ->and($report)->toContain('No feedback sends or retries')
        ->and($report)->toContain('No wallet reads or writes')
        ->and($report)->toContain('No money movement');
});

it('updates the cockpit compass for host integration slice 1H closure', function () {
    $compass = file_get_contents(dirname(__DIR__, 3).'/docs/ui-cockpit/COMPASS.md');

    expect($compass)->toContain('Completed Host Integration Slice 1H — Campaign Cockpit Read-Only Adoption Closure / Integration Readiness Update')
        ->and($compass)->toContain('Read-only Campaign Cockpit adoption is closed through Slice 1G')
        ->and($compass)->toContain('Dashboard campaign adoption panel is safe for read-only operator use')
        ->and($compass)->toContain('Pay Code Explorer campaign navigation context is safe for read-only operator orientation')
        ->and($compass)->toContain('Campaign mutation route scaffolding remains unauthorized');
});

it('preserves the historical read-only closure while exposing the subsequently authorized campaign routes', function () {
    $campaignCockpitRoutes = collect(Route::getRoutes())
        ->filter(fn ($route): bool => str_starts_with((string) $route->getName(), 'x-change.cockpit.campaign'));

    expect($campaignCockpitRoutes)->not->toBeEmpty()
        ->and(Route::has('x-change.cockpit.campaigns.index'))->toBeTrue()
        ->and(Route::has('x-change.cockpit.campaigns.show'))->toBeTrue();
});
