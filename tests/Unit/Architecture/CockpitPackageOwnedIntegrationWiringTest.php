<?php

declare(strict_types=1);

it('documents package-owned read-only cockpit integration wiring', function () {
    $reportPath = dirname(__DIR__, 3).'/docs/ui-cockpit/reports/023-package-owned-read-only-integration-wiring.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);

    expect($report)->toContain('# Host Integration Slice 1I — Package-Owned Read-Only Integration Wiring')
        ->and($report)->toContain('Move the read-only Settlement OS package wiring into `3neti/x-change` so the host application can remain dumb')
        ->and($report)->toContain('x-change now owns the Composer dependency wiring')
        ->and($report)->toContain('supersedes the earlier Host Integration Slice 1C assumption')
        ->and($report)->toContain('host application logic')
        ->and($report)->toContain('host route/controller wiring')
        ->and($report)->toContain('Pay Code generation through campaign')
        ->and($report)->toContain('journal writes')
        ->and($report)->toContain('action execution')
        ->and($report)->toContain('feedback sends or retries')
        ->and($report)->toContain('money movement');
});

it('keeps the package-owned integration decision in the cockpit and settlement compasses', function () {
    $cockpitCompass = file_get_contents(dirname(__DIR__, 3).'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents(dirname(__DIR__, 3).'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($cockpitCompass)->toContain('Completed Host Integration Slice 1I — Package-Owned Read-Only Integration Wiring')
        ->and($cockpitCompass)->toContain('x-change now owns Composer dependency wiring for `3neti/x-journal`, `3neti/x-action`, `3neti/x-feedback`, and `3neti/x-campaign`')
        ->and($cockpitCompass)->toContain('The host app remains dumb')
        ->and($settlementCompass)->toContain('Completed through Host Integration Slice 1I')
        ->and($settlementCompass)->toContain('host applications should remain dumb and should not duplicate Cockpit integration wiring')
        ->and($settlementCompass)->toContain('x-change Host Integration Slice 2 — Journal/action/feedback read-model hydration into Cockpit surfaces');
});

it('declares read-only integration packages as x-change runtime dependencies', function () {
    $composer = json_decode(
        file_get_contents(dirname(__DIR__, 3).'/composer.json'),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    expect($composer['require'])->toMatchArray([
        '3neti/x-action' => '^1.0',
        '3neti/x-campaign' => '^1.0',
        '3neti/x-feedback' => '^1.0',
        '3neti/x-journal' => '^1.0',
    ]);
});
