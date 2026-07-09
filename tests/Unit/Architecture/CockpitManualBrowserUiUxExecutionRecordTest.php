<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

it('documents the manual browser ui ux execution record with human visual confirmation', function () {
    $reportPath = dirname(__DIR__, 3).'/docs/ui-cockpit/reports/035-manual-browser-ui-ux-pass-execution-record.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents(dirname(__DIR__, 3).'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents(dirname(__DIR__, 3).'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    $expectedRoutes = [
        'x-change.cockpit.dashboard',
        'x-change.cockpit.quick-generate',
        'x-change.cockpit.pay-codes.index',
        'x-change.cockpit.pay-codes.show',
        'x-change.cockpit.pay-codes.distribution',
    ];

    foreach ($expectedRoutes as $routeName) {
        expect(Route::has($routeName))->toBeTrue()
            ->and($report)->toContain($routeName);
    }

    expect($report)->toContain('Host Validation Checkpoint 3 — Manual Browser UI/UX Pass Execution Record')
        ->and($report)->toContain('http://x-change-sandbox.test/x/cockpit')
        ->and($report)->toContain('Programmatic route/read-model record complete; human visual browser confirmation passed')
        ->and($report)->toContain('Human visual confirmation is recorded as `Pass`')
        ->and($report)->toContain('Human reviewer confirmed `/x/cockpit`')
        ->and($report)->toContain('can.mutate_vouchers = false')
        ->and($report)->toContain('can.move_money = false')
        ->and($report)->toContain('basic_cash')
        ->and($report)->toContain('divisible_open_three_slices_enforced_interval')
        ->and($report)->toContain('This checkpoint did not add:')
        ->and($report)->toContain('money movement')
        ->and($cockpitCompass)->toContain('Host Validation Checkpoint 3 — Manual Browser UI/UX Pass Execution Record')
        ->and($cockpitCompass)->toContain('Human visual browser confirmation is now recorded as `Pass`')
        ->and($cockpitCompass)->toContain('reports/035-manual-browser-ui-ux-pass-execution-record.md')
        ->and($settlementCompass)->toContain('x-change Host Validation Checkpoint 3 — Manual Browser UI/UX Pass Execution Record')
        ->and($settlementCompass)->toContain('Human visual browser confirmation is recorded as `Pass`');
});
