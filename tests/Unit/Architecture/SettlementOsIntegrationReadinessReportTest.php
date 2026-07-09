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
        ->and($report)->toContain('x-change Host Integration Slice 1 — Read-only Campaign Cockpit Adoption');
});

it('updates the overall Settlement OS compass with the next host integration slice', function () {
    $compass = file_get_contents(dirname(__DIR__, 3).'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($compass)->toContain('Current wave: Host Integration Readiness')
        ->and($compass)->toContain('Wave 5 — x-campaign complete through Phase 15')
        ->and($compass)->toContain('x-change Host Integration Slice 1 — Read-only Campaign Cockpit Adoption')
        ->and($compass)->toContain('Mutation route scaffolding remains unauthorized');
});
