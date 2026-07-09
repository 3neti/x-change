<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

it('documents the manual browser ui ux pass checklist for read-only cockpit routes', function () {
    $reportPath = dirname(__DIR__, 3).'/docs/ui-cockpit/reports/034-manual-browser-ui-ux-pass-checklist.md';

    expect(file_exists($reportPath))->toBeTrue();

    $report = file_get_contents($reportPath);
    $cockpitCompass = file_get_contents(dirname(__DIR__, 3).'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents(dirname(__DIR__, 3).'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    $expectedRoutes = [
        'x-change.cockpit.dashboard' => 'x-change/cockpit/Dashboard',
        'x-change.cockpit.quick-generate' => 'x-change/cockpit/QuickGenerate',
        'x-change.cockpit.pay-codes.index' => 'x-change/cockpit/PayCodeExplorer',
        'x-change.cockpit.pay-codes.show' => 'x-change/cockpit/VoucherDetail',
        'x-change.cockpit.pay-codes.distribution' => 'x-change/cockpit/DistributionWorkspace',
    ];

    foreach ($expectedRoutes as $routeName => $component) {
        expect(Route::has($routeName))->toBeTrue()
            ->and($report)->toContain($routeName)
            ->and($report)->toContain($component);
    }

    $cockpitRoutes = collect(Route::getRoutes())
        ->filter(fn ($route): bool => array_key_exists((string) $route->getName(), $expectedRoutes));

    $cockpitRoutes->each(fn ($route) => expect($route->methods())->toContain('GET'));

    expect($report)->toContain('Host Validation Checkpoint 2 — Manual Browser UI/UX Pass Checklist')
        ->and($report)->toContain('http://x-change-sandbox.test/x/cockpit')
        ->and($report)->toContain('basic_cash')
        ->and($report)->toContain('divisible_open_three_slices_enforced_interval')
        ->and($report)->toContain('Stop and report before proceeding')
        ->and($report)->toContain('This checkpoint did not add:')
        ->and($report)->toContain('money movement')
        ->and($cockpitCompass)->toContain('Completed Host Validation Checkpoint 2 — Manual Browser UI/UX Pass Checklist')
        ->and($cockpitCompass)->toContain('reports/034-manual-browser-ui-ux-pass-checklist.md')
        ->and($settlementCompass)->toContain('x-change Host Validation Checkpoint 2 — Manual Browser UI/UX Pass Checklist')
        ->and($settlementCompass)->toContain('The manual browser pass checklist is route-aware for Dashboard, Quick Generate, Pay Code Explorer, Voucher Detail, and Distribution Workspace');
});
