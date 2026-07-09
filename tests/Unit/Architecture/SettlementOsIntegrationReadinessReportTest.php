<?php

declare(strict_types=1);

it('documents cross-package Settlement OS integration readiness', function () {
    $reportPath = dirname(__DIR__, 3).'/docs/architecture/INTEGRATION_READINESS_REPORT.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);

    expect($report)->toContain('# Settlement OS Integration Readiness Report')
        ->and($report)->toContain('voucher / execution engine')
        ->and($report)->toContain('x-journal')
        ->and($report)->toContain('x-action')
        ->and($report)->toContain('x-feedback')
        ->and($report)->toContain('x-change Cockpit')
        ->and($report)->toContain('x-campaign')
        ->and($report)->toContain('x-change Host Integration Slice 1 — Read-only Campaign Cockpit Adoption')
        ->and($report)->toContain('Completed through Host Integration Slice 1I')
        ->and($report)->toContain('Dashboard campaign adoption panel')
        ->and($report)->toContain('Pay Code Explorer campaign navigation context')
        ->and($report)->toContain('Package-owned read-only dependency wiring')
        ->and($report)->toContain('No dedicated campaign workspace route')
        ->and($report)->toContain('Campaign mutation route scaffolding remains unauthorized');
});

it('updates the overall Settlement OS compass with the next host integration slice', function () {
    $compass = file_get_contents(dirname(__DIR__, 3).'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($compass)->toContain('Current wave: Host Integration Readiness')
        ->and($compass)->toContain('Wave 5 — x-campaign complete through Phase 15')
        ->and($compass)->toContain('Cockpit navigation hardening complete')
        ->and($compass)->toContain('Slice 27 + navigation hardening complete')
        ->and($compass)->toContain('x-change Host Integration Slice 1 — Read-only Campaign Cockpit Adoption')
        ->and($compass)->toContain('Completed through Host Integration Slice 1I')
        ->and($compass)->toContain('host applications should remain dumb and should not duplicate Cockpit integration wiring')
        ->and($compass)->toContain('Mutation route scaffolding remains unauthorized')
        ->and($compass)->toContain('## Resolved Compass Questions')
        ->and($compass)->toContain('x-journal uses `spatie/laravel-data` for DTOs')
        ->and($compass)->not->toContain('Should Phase 1 use `spatie/laravel-data` DTOs immediately')
        ->and($compass)->not->toContain('Should the new x-journal package be wired into the host app path repositories now');
});
