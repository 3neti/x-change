<?php

declare(strict_types=1);

it('documents pay code explorer status badge polish slice 1', function (): void {
    $packageRoot = dirname(__DIR__, 3);

    $report = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/590-pay-code-explorer-status-badge-polish-slice-1.md');
    $closure = file_get_contents($packageRoot.'/docs/ui-cockpit/reports/591-pay-code-explorer-status-badge-polish-slice-2-closure.md');
    $component = file_get_contents($packageRoot.'/resources/js/cockpit/components/CockpitPayCodeResultsTable.vue');
    $hostComponent = file_get_contents($packageRoot.'/../../resources/js/cockpit/components/CockpitPayCodeResultsTable.vue');
    $frontendTest = file_get_contents($packageRoot.'/tests/frontend/cockpit/CockpitPayCodeExplorerHydration.test.ts');
    $cockpitCompass = file_get_contents($packageRoot.'/docs/ui-cockpit/COMPASS.md');
    $settlementCompass = file_get_contents($packageRoot.'/docs/architecture/SETTLEMENT_OS_COMPASS.md');

    expect($report)
        ->toContain('Pay Code Explorer Status Badge Polish — Slice 1')
        ->toContain('operator-facing Title Case')
        ->toContain('Presentation-only status badge polish')
        ->and($component)->toContain('function displayStatus')
        ->and($component)->toContain('function statusBadgeClass')
        ->and($component)->toContain('cockpit-pay-code-status-badge')
        ->and($component)->toContain('cockpit-pay-code-mobile-status-badge')
        ->and($component)->toContain('bg-emerald-50')
        ->and($component)->toContain('bg-amber-50')
        ->and($component)->toContain('bg-rose-50')
        ->and($hostComponent)->toContain('function displayStatus')
        ->and($hostComponent)->toContain('function statusBadgeClass')
        ->and($hostComponent)->toContain('cockpit-pay-code-status-badge')
        ->and($hostComponent)->toContain('cockpit-pay-code-mobile-status-badge')
        ->and($frontendTest)->toContain('renders scan-friendly status badges')
        ->and($frontendTest)->toContain('Awaiting Approval')
        ->and($closure)->toContain('Pay Code Explorer Status Badge Polish — Slice 2 Closure')
        ->and($closure)->toContain('php artisan x-change:doctor --assets --no-interaction')
        ->and($closure)->toContain('php artisan dusk tests/Browser/CockpitPayCodeExplorerFilterSmokeTest.php')
        ->and($closure)->toContain('Unknown statuses fall back to a neutral badge.')
        ->and($cockpitCompass)->toContain('Pay Code Explorer Status Badge Polish Slice 1')
        ->and($cockpitCompass)->toContain('reports/590-pay-code-explorer-status-badge-polish-slice-1.md')
        ->and($cockpitCompass)->toContain('Pay Code Explorer Status Badge Polish Slice 2')
        ->and($cockpitCompass)->toContain('reports/591-pay-code-explorer-status-badge-polish-slice-2-closure.md')
        ->and($settlementCompass)->toContain('Pay Code Explorer Status Badge Polish — Slice 1')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/590-pay-code-explorer-status-badge-polish-slice-1.md')
        ->and($settlementCompass)->toContain('Pay Code Explorer Status Badge Polish — Slice 2')
        ->and($settlementCompass)->toContain('../ui-cockpit/reports/591-pay-code-explorer-status-badge-polish-slice-2-closure.md');
});
